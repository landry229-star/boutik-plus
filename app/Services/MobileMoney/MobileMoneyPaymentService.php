<?php

namespace App\Services\MobileMoney;

use App\Models\Facture;
use App\Models\MobileMoneyPayment;
use App\Models\MouvementCaisse;
use App\Models\MouvementStock;
use App\Models\Produit;
use Illuminate\Support\Facades\DB;

class MobileMoneyPaymentService
{
    public function findForTransaction(array $transaction): ?MobileMoneyPayment
    {
        $transactionId = $transaction['id'] ?? null;

        if (! is_int($transactionId) && (! is_string($transactionId) || ! ctype_digit($transactionId))) {
            return null;
        }

        $payment = MobileMoneyPayment::query()
            ->where('provider', 'fedapay')
            ->where('external_reference', (string) $transactionId)
            ->first();

        if ($payment) {
            return $payment;
        }

        $metadata = is_array($transaction['custom_metadata'] ?? null)
            ? $transaction['custom_metadata']
            : [];
        $reference = $metadata['reference'] ?? $metadata['local_reference'] ?? null;

        if (! is_string($reference) || $reference === '') {
            return null;
        }

        return MobileMoneyPayment::query()
            ->where('provider', 'fedapay')
            ->where('reference', $reference)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    public function synchronize(MobileMoneyPayment $payment, array $transaction): string
    {
        return DB::transaction(function () use ($payment, $transaction): string {
            $payment = MobileMoneyPayment::query()->lockForUpdate()->findOrFail($payment->id);
            $facture = Facture::query()->lockForUpdate()->findOrFail($payment->facture_id);

            $this->assertTransactionMatches($payment, $transaction);

            $status = strtolower((string) ($transaction['status'] ?? ''));
            $response = array_merge($payment->response ?? [], [
                'transaction_id' => (string) $transaction['id'],
                'provider_status' => $status,
            ]);

            if ($payment->status !== 'pending' || $facture->statut !== 'en_attente') {
                return $payment->status;
            }

            if ($status === 'approved') {
                MouvementCaisse::create([
                    'date_jour' => now()->toDateString(),
                    'moyen_paiement' => 'mobile_money',
                    'user_id' => $facture->user_id,
                    'utilisateur_nom_snapshot' => $facture->utilisateur_nom_snapshot,
                    'facture_id' => $facture->id,
                    'type' => 'entree',
                    'montant' => $payment->montant,
                    'description' => 'Vente facture '.$facture->numero,
                ]);

                $facture->update(['statut' => 'payee']);
                $payment->update([
                    'status' => 'approved',
                    'confirmed_at' => now(),
                    'response' => $response,
                ]);

                return 'approved';
            }

            if (in_array($status, ['canceled', 'declined', 'failed'], true)) {
                $this->releaseReservation($facture, $status);

                $facture->update([
                    'statut' => 'annulee',
                    'annulee_at' => now(),
                    'annulee_par_nom_snapshot' => 'FedaPay',
                    'motif_annulation' => 'Paiement FedaPay '.$status,
                ]);
                $payment->update([
                    'status' => $status,
                    'response' => $response,
                ]);

                return $status;
            }

            $payment->update(['response' => $response]);

            return 'pending';
        }, 3);
    }

    public function rejectBeforeCheckout(MobileMoneyPayment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $payment = MobileMoneyPayment::query()->lockForUpdate()->findOrFail($payment->id);
            $facture = Facture::query()->lockForUpdate()->findOrFail($payment->facture_id);

            if ($payment->status !== 'pending' || $facture->statut !== 'en_attente') {
                return;
            }

            $this->releaseReservation($facture, 'failed');

            $facture->update([
                'statut' => 'annulee',
                'annulee_at' => now(),
                'annulee_par_nom_snapshot' => 'FedaPay',
                'motif_annulation' => 'La création du paiement FedaPay a été refusée.',
            ]);
            $payment->update(['status' => 'failed']);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $transaction
     */
    private function assertTransactionMatches(MobileMoneyPayment $payment, array $transaction): void
    {
        $transactionId = $transaction['id'] ?? null;

        if ((! is_int($transactionId) && ! is_string($transactionId))
            || ! ctype_digit((string) $transactionId)
            || ($payment->external_reference !== null
                && ! hash_equals((string) $payment->external_reference, (string) $transactionId))) {
            throw new MobileMoneyIntegrityException('La transaction FedaPay ne correspond pas au paiement local.');
        }

        $transactionAmount = $transaction['amount'] ?? null;

        if ((! is_int($transactionAmount) && ! is_string($transactionAmount))
            || filter_var($transactionAmount, FILTER_VALIDATE_INT) === false
            || (int) $transactionAmount !== (int) round((float) $payment->montant)) {
            throw new MobileMoneyIntegrityException('Le montant FedaPay ne correspond pas au paiement local.');
        }

        $providerCurrency = is_array($transaction['currency'] ?? null)
            ? ($transaction['currency']['iso'] ?? $transaction['currency']['code'] ?? null)
            : null;
        $currency = $providerCurrency ?? (is_string($transaction['currency'] ?? null) ? $transaction['currency'] : null);

        if ($currency !== null && strtoupper((string) $currency) !== $payment->devise) {
            throw new MobileMoneyIntegrityException('La devise FedaPay ne correspond pas au paiement local.');
        }

        $metadata = is_array($transaction['custom_metadata'] ?? null)
            ? $transaction['custom_metadata']
            : [];
        $metadataReference = $metadata['reference'] ?? $metadata['local_reference'] ?? null;

        if ($metadataReference !== null && ! hash_equals($payment->reference, (string) $metadataReference)) {
            throw new MobileMoneyIntegrityException('La référence locale du paiement ne correspond pas à FedaPay.');
        }

        if ($payment->external_reference === null && $metadataReference === null) {
            throw new MobileMoneyIntegrityException('La transaction FedaPay ne contient aucune référence locale vérifiable.');
        }

        if ($payment->external_reference === null) {
            $payment->update(['external_reference' => (string) $transactionId]);
        }
    }

    private function releaseReservation(Facture $facture, string $providerStatus): void
    {
        $quantitiesByProduct = $facture->lignes()
            ->get()
            ->groupBy('produit_id')
            ->map(fn ($lines) => $lines->sum('quantite'));
        $products = Produit::query()
            ->whereIn('id', $quantitiesByProduct->keys())
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($quantitiesByProduct as $productId => $quantity) {
            $product = $products->get($productId);

            if (! $product) {
                throw new MobileMoneyIntegrityException('Un produit réservé pour cette facture est introuvable.');
            }

            $product->increment('quantite_stock', $quantity);

            MouvementStock::create([
                'produit_id' => $product->id,
                'utilisateur_nom_snapshot' => 'FedaPay',
                'facture_id' => $facture->id,
                'type' => 'entree',
                'quantite' => $quantity,
                'motif' => 'Libération du stock après paiement FedaPay '.$providerStatus,
            ]);
        }
    }
}
