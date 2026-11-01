<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PerformanceSeeder extends Seeder
{
    private const PRODUCT_COUNT = 500;

    private const INVOICE_COUNT = 5000;

    private const PURCHASE_COUNT = 500;

    private const INVOICE_PREFIX = 'PERF-';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $connection = DB::connection();

        if (
            app()->isProduction()
            || $connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'boutik'
        ) {
            throw new RuntimeException('Le jeu de performance est réservé à la base MySQL locale boutik.');
        }

        $invoicePrefix = self::INVOICE_PREFIX;

        if ($connection->table('factures')->where('numero', 'like', $invoicePrefix.'%')->exists()) {
            $this->command?->info('Le jeu de performance est déjà présent. Aucune donnée n’a été ajoutée.');

            return;
        }

        if (
            $connection->table('produits')->where('code_barre', 'PERF-000001')->exists()
            || $connection->table('fournisseurs')->where('nom', 'Fournisseur Performance 001')->exists()
            || $connection->table('achats')->where('notes', 'Jeu de performance PERF-ACH-00001')->exists()
        ) {
            throw new RuntimeException('Un jeu de performance incomplet existe déjà. Aucune donnée supplémentaire n’a été ajoutée.');
        }

        $patron = $connection->table('users')
            ->where('email', config('boutik.patron.email'))
            ->where('role', 'patron')
            ->first();

        if (! $patron) {
            throw new RuntimeException('Créez d’abord le compte patron avec le seeder DatabaseSeeder.');
        }

        $now = now()->toDateTimeString();
        $historyStart = now()->subDays(731)->toDateTimeString();
        $categories = ['Alimentation', 'Boissons', 'Hygiène', 'Entretien', 'Accessoires'];
        $supplierNames = [];
        $suppliers = [];

        mt_srand(20260929);

        for ($index = 1; $index <= 25; $index++) {
            $name = sprintf('Fournisseur Performance %03d', $index);
            $supplierNames[] = $name;
            $suppliers[] = [
                'nom' => $name,
                'telephone' => sprintf('970%05d', $index),
                'adresse' => sprintf('Zone commerciale %02d, Cotonou', $index),
                'notes' => 'Jeu de données de performance',
                'actif' => $index > 2,
                'created_at' => $historyStart,
                'updated_at' => $now,
            ];
        }

        $productCodes = [];
        $productRows = [];
        $purchasePrices = [];
        $startingStock = 1500;

        for ($index = 1; $index <= self::PRODUCT_COUNT; $index++) {
            $code = sprintf('PERF-%06d', $index);
            $purchasePrice = mt_rand(100, 500) * 10;
            $productCodes[] = $code;
            $purchasePrices[$code] = $purchasePrice;
            $productRows[] = [
                'nom' => sprintf('Produit Performance %04d', $index),
                'categorie' => $categories[($index - 1) % count($categories)],
                'code_barre' => $code,
                'prix_achat' => $purchasePrice,
                'prix_vente' => $purchasePrice + mt_rand(50, 200) * 10,
                'quantite_stock' => $startingStock,
                'seuil_alerte' => mt_rand(5, 25),
                'actif' => $index % 50 !== 0,
                'created_at' => $historyStart,
                'updated_at' => $now,
            ];
        }

        $taxEnabled = (bool) config('boutik.tax.enabled');
        $taxRate = (float) config('boutik.tax.rate');
        $taxLabel = config('boutik.tax.label');

        $connection->transaction(function () use (
            $connection,
            $historyStart,
            $patron,
            $supplierNames,
            $suppliers,
            $productCodes,
            $productRows,
            $purchasePrices,
            $startingStock,
            $taxEnabled,
            $taxRate,
            $taxLabel,
            $invoicePrefix
        ): void {
            $this->insertInChunks('fournisseurs', $suppliers);
            $supplierIds = $connection->table('fournisseurs')
                ->whereIn('nom', $supplierNames)
                ->pluck('id')
                ->all();

            $this->insertInChunks('produits', $productRows);
            $products = $connection->table('produits')
                ->whereIn('code_barre', $productCodes)
                ->get(['id', 'code_barre', 'prix_vente'])
                ->keyBy('code_barre');

            $stockMovements = [];
            $initialStockByProduct = [];
            foreach ($productCodes as $code) {
                $product = $products->get($code);
                $initialStockByProduct[$product->id] = $startingStock;
                $stockMovements[] = [
                    'produit_id' => $product->id,
                    'user_id' => $patron->id,
                    'type' => 'entree',
                    'quantite' => $startingStock,
                    'motif' => 'Stock initial - jeu de performance',
                    'created_at' => $historyStart,
                    'updated_at' => $historyStart,
                ];
            }

            $purchaseNumbers = [];
            $purchaseRows = [];
            $purchaseLineRows = [];
            $purchaseCashRows = [];
            $stockAddedByProduct = array_fill_keys(array_keys($initialStockByProduct), 0);

            for ($index = 1; $index <= self::PURCHASE_COUNT; $index++) {
                $reference = sprintf('PERF-ACH-%05d', $index);
                $date = now()->subDays(mt_rand(0, 730))->toDateString();
                $supplierId = $supplierIds[($index - 1) % count($supplierIds)];
                $selectedCodes = array_rand($productCodes, mt_rand(2, 4));
                $selectedCodes = is_array($selectedCodes) ? $selectedCodes : [$selectedCodes];
                $lineData = [];
                $total = 0;

                foreach ($selectedCodes as $selectedCodeIndex) {
                    $code = $productCodes[$selectedCodeIndex];
                    $product = $products->get($code);
                    $quantity = mt_rand(5, 40);
                    $unitPrice = $purchasePrices[$code];
                    $subtotal = $quantity * $unitPrice;
                    $total += $subtotal;
                    $stockAddedByProduct[$product->id] += $quantity;
                    $lineData[] = [
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'subtotal' => $subtotal,
                    ];
                }

                $purchaseNumbers[] = 'Jeu de performance '.$reference;
                $purchaseRows[] = [
                    'fournisseur_id' => $supplierId,
                    'user_id' => $patron->id,
                    'montant_total' => $total,
                    'date_achat' => $date,
                    'notes' => 'Jeu de performance '.$reference,
                    'created_at' => $date.' 10:00:00',
                    'updated_at' => $date.' 10:00:00',
                ];

                foreach ($lineData as $line) {
                    $purchaseLineRows[] = [
                        'reference' => 'Jeu de performance '.$reference,
                        'produit_id' => $line['product_id'],
                        'quantite' => $line['quantity'],
                        'prix_achat_unitaire' => $line['unit_price'],
                        'created_at' => $date.' 10:00:00',
                        'updated_at' => $date.' 10:00:00',
                    ];
                    $stockMovements[] = [
                        'produit_id' => $line['product_id'],
                        'user_id' => $patron->id,
                        'type' => 'entree',
                        'quantite' => $line['quantity'],
                        'motif' => 'Achat fournisseur - '.$reference,
                        'created_at' => $date.' 10:00:00',
                        'updated_at' => $date.' 10:00:00',
                    ];
                }

                $payment = (int) floor($total * mt_rand(35, 100) / 100);
                if ($payment > 0) {
                    $purchaseCashRows[] = [
                        'date_jour' => $date,
                        'type' => 'sortie',
                        'montant' => $payment,
                        'moyen_paiement' => $index % 3 === 0 ? 'mobile_money' : 'especes',
                        'user_id' => $patron->id,
                        'achat_reference' => 'Jeu de performance '.$reference,
                        'description' => 'Paiement fournisseur - '.$reference,
                        'created_at' => $date.' 11:00:00',
                        'updated_at' => $date.' 11:00:00',
                    ];
                }
            }

            $this->insertInChunks('achats', $purchaseRows);
            $purchases = $connection->table('achats')
                ->whereIn('notes', $purchaseNumbers)
                ->get(['id', 'notes'])
                ->keyBy('notes');

            foreach ($purchaseLineRows as &$line) {
                $purchase = $purchases->get($line['reference']);
                $line['achat_id'] = $purchase->id;
                unset($line['reference']);
            }
            unset($line);

            $this->insertInChunks('achat_lignes', $purchaseLineRows);

            foreach ($purchaseCashRows as &$movement) {
                $purchase = $purchases->get($movement['achat_reference']);
                $movement['achat_id'] = $purchase->id;
                unset($movement['achat_reference']);
            }
            unset($movement);

            $invoiceRows = [];
            $invoiceLineRows = [];
            $invoiceCashRows = [];
            $invoiceStockRows = [];
            $invoiceNumbers = [];
            $soldByProduct = array_fill_keys(array_keys($initialStockByProduct), 0);
            for ($index = 1; $index <= self::INVOICE_COUNT; $index++) {
                $number = $invoicePrefix.sprintf('%05d', $index);
                $date = now()->subDays(mt_rand(0, 730))->setTime(mt_rand(8, 20), mt_rand(0, 59))->toDateTimeString();
                $isCancelled = $index % 20 === 0;
                $paymentMethod = $index % 3 === 0 ? 'mobile_money' : 'especes';
                $selectedProductIndexes = array_rand($productCodes, mt_rand(2, 4));
                $selectedProductIndexes = is_array($selectedProductIndexes) ? $selectedProductIndexes : [$selectedProductIndexes];
                $lineData = [];
                $subtotal = 0;

                foreach ($selectedProductIndexes as $selectedProductIndex) {
                    $product = $products->get($productCodes[$selectedProductIndex]);
                    $quantity = mt_rand(1, 5);
                    $lineSubtotal = $quantity * (float) $product->prix_vente;
                    $subtotal += $lineSubtotal;
                    $lineData[] = [
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $product->prix_vente,
                        'subtotal' => $lineSubtotal,
                    ];

                    $invoiceStockRows[] = [
                        'produit_id' => $product->id,
                        'user_id' => $patron->id,
                        'type' => 'sortie',
                        'quantite' => $quantity,
                        'motif' => 'Vente facture '.$number,
                        'created_at' => $date,
                        'updated_at' => $date,
                    ];

                    if ($isCancelled) {
                        $invoiceStockRows[] = [
                            'produit_id' => $product->id,
                            'user_id' => $patron->id,
                            'type' => 'entree',
                            'quantite' => $quantity,
                            'motif' => 'Annulation facture '.$number,
                            'created_at' => $date,
                            'updated_at' => $date,
                        ];
                    } else {
                        $soldByProduct[$product->id] += $quantity;
                    }
                }

                $taxAmount = $taxEnabled ? (int) round($subtotal * $taxRate / 100) : 0;
                $total = $subtotal + $taxAmount;
                $invoiceNumbers[] = $number;
                $invoiceRows[] = [
                    'user_id' => $patron->id,
                    'numero' => $number,
                    'client_nom' => sprintf('Client Performance %04d', (($index - 1) % 1000) + 1),
                    'client_telephone' => sprintf('22997%07d', $index),
                    'client_email' => sprintf('client.performance.%04d@example.test', (($index - 1) % 1000) + 1),
                    'montant_ht' => $subtotal,
                    'montant_taxe' => $taxAmount,
                    'taux_taxe' => $taxEnabled ? $taxRate : 0,
                    'libelle_taxe' => $taxEnabled ? $taxLabel : null,
                    'montant_total' => $total,
                    'mode_paiement' => $paymentMethod,
                    'statut' => $isCancelled ? 'annulee' : 'payee',
                    'annulee_at' => $isCancelled ? $date : null,
                    'annulee_par_user_id' => $isCancelled ? $patron->id : null,
                    'motif_annulation' => $isCancelled ? 'Annulation de démonstration' : null,
                    'created_at' => $date,
                    'updated_at' => $date,
                ];

                foreach ($lineData as $line) {
                    $invoiceLineRows[] = [
                        'numero' => $number,
                        'produit_id' => $line['product_id'],
                        'quantite' => $line['quantity'],
                        'prix_unitaire' => $line['unit_price'],
                        'sous_total' => $line['subtotal'],
                        'created_at' => $date,
                        'updated_at' => $date,
                    ];
                }

                $invoiceCashRows[] = [
                    'date_jour' => substr($date, 0, 10),
                    'type' => 'entree',
                    'montant' => $total,
                    'moyen_paiement' => $paymentMethod,
                    'user_id' => $patron->id,
                    'facture_numero' => $number,
                    'description' => 'Vente facture '.$number,
                    'created_at' => $date,
                    'updated_at' => $date,
                ];

                if ($isCancelled) {
                    $invoiceCashRows[] = [
                        'date_jour' => substr($date, 0, 10),
                        'type' => 'sortie',
                        'montant' => $total,
                        'moyen_paiement' => $paymentMethod,
                        'user_id' => $patron->id,
                        'facture_numero' => $number,
                        'description' => 'Remboursement facture '.$number,
                        'created_at' => $date,
                        'updated_at' => $date,
                    ];
                }
            }

            $this->insertInChunks('factures', $invoiceRows);
            $invoices = $connection->table('factures')
                ->whereIn('numero', $invoiceNumbers)
                ->get(['id', 'numero'])
                ->keyBy('numero');

            foreach ($invoiceLineRows as &$line) {
                $invoice = $invoices->get($line['numero']);
                $line['facture_id'] = $invoice->id;
                unset($line['numero']);
            }
            unset($line);

            foreach ($invoiceCashRows as &$movement) {
                $invoice = $invoices->get($movement['facture_numero']);
                $movement['facture_id'] = $invoice->id;
                unset($movement['facture_numero']);
            }
            unset($movement);

            foreach ($invoiceStockRows as &$movement) {
                if (str_starts_with($movement['motif'], 'Vente facture ')) {
                    $number = substr($movement['motif'], strlen('Vente facture '));
                    $movement['facture_id'] = $invoices->get($number)->id;
                } else {
                    $number = substr($movement['motif'], strlen('Annulation facture '));
                    $movement['facture_id'] = $invoices->get($number)->id;
                }
            }
            unset($movement);

            $this->insertInChunks('facture_lignes', $invoiceLineRows);
            $this->insertInChunks('mouvements_caisse', $purchaseCashRows);
            $this->insertInChunks('mouvements_caisse', $invoiceCashRows);
            $this->insertInChunks('mouvements_stock', $stockMovements);
            $this->insertInChunks('mouvements_stock', $invoiceStockRows);

            foreach ($initialStockByProduct as $productId => $quantity) {
                $connection->table('produits')
                    ->where('id', $productId)
                    ->update([
                        'quantite_stock' => $quantity + $stockAddedByProduct[$productId] - $soldByProduct[$productId],
                    ]);
            }
        }, 3);

        $this->command?->info(sprintf(
            'Jeu de performance créé : %d produits, %d fournisseurs, %d achats et %d factures.',
            self::PRODUCT_COUNT,
            25,
            self::PURCHASE_COUNT,
            self::INVOICE_COUNT
        ));
    }

    /**
     * @param  list<array<string, bool|float|int|string|null>>  $rows
     */
    private function insertInChunks(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
