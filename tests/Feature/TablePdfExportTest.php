<?php

namespace Tests\Feature;

use App\Models\Facture;
use App\Models\Fournisseur;
use App\Models\Produit;
use App\Models\User;
use App\Services\TablePdfExporter;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TablePdfExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_export_contains_all_matching_rows_across_pages(): void
    {
        $employee = User::factory()->create([
            'name' => 'Employé exporté',
            'role' => 'employe',
        ]);

        for ($index = 1; $index <= 26; $index++) {
            Produit::create([
                'nom' => sprintf('Cible %02d', $index),
                'prix_achat' => 100,
                'prix_vente' => 150,
                'quantite_stock' => 5,
                'seuil_alerte' => 1,
            ]);
        }

        for ($index = 1; $index <= 4; $index++) {
            Produit::create([
                'nom' => sprintf('Autre %02d', $index),
                'prix_achat' => 100,
                'prix_vente' => 150,
                'quantite_stock' => 5,
                'seuil_alerte' => 1,
            ]);
        }

        $exporter = \Mockery::mock(TablePdfExporter::class)->makePartial();
        $exporter->shouldReceive('download')
            ->once()
            ->withArgs(function (string $title, string $fileName, array $sections, array $criteria): bool {
                $this->assertSame('Catalogue des produits', $title);
                $this->assertSame('produits', $fileName);
                $this->assertCount(26, $sections[0]['rows']);
                $this->assertSame('Cible 01', $sections[0]['rows'][0][0]);
                $this->assertSame('Cible 26', $sections[0]['rows'][25][0]);
                $this->assertSame(['Recherche : Cible', 'Catalogue : actif'], $criteria);

                return true;
            })
            ->andReturn(response('PDF'));
        $this->app->instance(TablePdfExporter::class, $exporter);

        $this->actingAs($employee)
            ->get(route('produits.index', ['q' => 'Cible', 'page' => 2, 'pdf' => 1]))
            ->assertOk();
    }

    public function test_date_filters_limit_product_supplier_and_team_exports(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $employee = User::factory()->create([
            'name' => 'Employé exporté',
            'role' => 'employe',
        ]);
        $employee->forceFill([
            'created_at' => '2026-09-05 10:00:00',
            'updated_at' => '2026-09-05 10:00:00',
        ])->save();
        $oldEmployee = User::factory()->create([
            'name' => 'Employé hors période',
            'role' => 'employe',
        ]);
        $oldEmployee->forceFill([
            'created_at' => '2026-08-20 10:00:00',
            'updated_at' => '2026-08-20 10:00:00',
        ])->save();

        foreach ([
            ['Produit exporté', '2026-09-05 10:00:00'],
            ['Produit hors période', '2026-08-20 10:00:00'],
        ] as [$name, $createdAt]) {
            $product = Produit::create([
                'nom' => $name,
                'prix_achat' => 100,
                'prix_vente' => 150,
                'quantite_stock' => 5,
                'seuil_alerte' => 1,
            ]);
            $product->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        foreach ([
            ['Fournisseur exporté', '2026-09-05 10:00:00'],
            ['Fournisseur hors période', '2026-08-20 10:00:00'],
        ] as [$name, $createdAt]) {
            $supplier = Fournisseur::create(['nom' => $name]);
            $supplier->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        $exporter = \Mockery::mock(TablePdfExporter::class)->makePartial();
        $exporter->shouldReceive('download')
            ->times(3)
            ->withArgs(function (string $title, string $fileName, array $sections, array $criteria): bool {
                $expectedRow = match ($title) {
                    'Catalogue des produits' => 'Produit exporté',
                    'Liste des fournisseurs' => 'Fournisseur exporté',
                    'Liste de l’équipe' => 'Employé exporté',
                };

                $this->assertCount(1, $sections[0]['rows']);
                $this->assertContains($expectedRow, $sections[0]['rows'][0]);
                $this->assertContains('Créé du : 2026-09-01', $criteria);
                $this->assertContains('Créé au : 2026-09-10', $criteria);

                return true;
            })
            ->andReturn(response('PDF'));
        $this->app->instance(TablePdfExporter::class, $exporter);

        $dateRange = [
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-09-10',
            'pdf' => 1,
        ];

        $this->actingAs($employee)
            ->get(route('produits.index', $dateRange))
            ->assertOk();
        $this->actingAs($patron)
            ->get(route('fournisseurs.index', $dateRange))
            ->assertOk();
        $this->actingAs($patron)
            ->get(route('utilisateurs.index', [...$dateRange, 'role' => 'employe']))
            ->assertOk();
    }

    public function test_pdf_export_template_renders_a_structured_table(): void
    {
        $html = view('pdf.table-export', [
            'title' => 'Catalogue produits',
            'sections' => [[
                'title' => 'Produits (1)',
                'headers' => ['Nom', 'Stock'],
                'rows' => [['Riz local', 12]],
            ]],
            'criteria' => [],
            'generatedAt' => Carbon::parse('2026-09-29 17:00:00'),
            'company' => [
                'name' => 'Boutique Exemple',
                'address' => null,
                'phone' => null,
                'email' => null,
                'ifu' => null,
                'rccm' => null,
            ],
            'logoDataUri' => null,
        ])->render();

        $this->assertStringContainsString('<table class="data-table">', $html);
        $this->assertStringContainsString('<th>Nom</th>', $html);
        $this->assertStringContainsString('<td>Riz local</td>', $html);
        $this->assertStringNotContainsString('<pre', $html);
    }

    public function test_invoice_export_uses_the_selected_date_range(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $matchingInvoice = Facture::create([
            'numero' => 'FAC-DATE-IN',
            'client_nom' => 'Client dans la période',
            'montant_total' => 1500,
            'mode_paiement' => 'especes',
            'statut' => 'payee',
        ]);
        $matchingInvoice->forceFill([
            'created_at' => '2026-09-05 10:00:00',
            'updated_at' => '2026-09-05 10:00:00',
        ])->save();
        $outsideInvoice = Facture::create([
            'numero' => 'FAC-DATE-OUT',
            'client_nom' => 'Client hors période',
            'montant_total' => 2500,
            'mode_paiement' => 'mobile_money',
            'statut' => 'payee',
        ]);
        $outsideInvoice->forceFill([
            'created_at' => '2026-08-20 10:00:00',
            'updated_at' => '2026-08-20 10:00:00',
        ])->save();

        $exporter = \Mockery::mock(TablePdfExporter::class)->makePartial();
        $exporter->shouldReceive('download')
            ->once()
            ->withArgs(function (string $title, string $fileName, array $sections, array $criteria): bool {
                $this->assertSame('Liste des factures', $title);
                $this->assertSame('factures', $fileName);
                $this->assertCount(1, $sections[0]['rows']);
                $this->assertSame('FAC-DATE-IN', $sections[0]['rows'][0][0]);
                $this->assertContains('Du : 2026-09-01', $criteria);
                $this->assertContains('Au : 2026-09-10', $criteria);

                return true;
            })
            ->andReturn(response('PDF'));
        $this->app->instance(TablePdfExporter::class, $exporter);

        $this->actingAs($patron)
            ->get(route('factures.index', [
                'date_debut' => '2026-09-01',
                'date_fin' => '2026-09-10',
                'pdf' => 1,
            ]))
            ->assertOk();
    }

    public function test_shop_user_can_download_each_table_and_dashboard_report_as_pdf(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);

        $exports = [
            ['produits.index', [], 'produits.pdf'],
            ['factures.index', [], 'factures.pdf'],
            ['fournisseurs.index', [], 'fournisseurs.pdf'],
            ['achats.index', [], 'achats-fournisseurs.pdf'],
            ['caisse.index', ['date' => now()->toDateString()], 'journal-de-caisse.pdf'],
            ['utilisateurs.index', [], 'equipe.pdf'],
            ['dashboard.index', [], 'rapport-tableau-de-bord.pdf'],
            ['dashboard.index', ['table' => 'paiements'], 'rapport-tableau-de-bord.pdf'],
            ['dashboard.index', ['table' => 'top_produits'], 'rapport-tableau-de-bord.pdf'],
            ['dashboard.index', ['table' => 'alertes_stock'], 'rapport-tableau-de-bord.pdf'],
        ];

        foreach ($exports as [$routeName, $filters, $fileName]) {
            $response = $this->actingAs($patron)
                ->get(route($routeName, [...$filters, 'pdf' => 1]));

            $response->assertDownload($fileName);
            $this->assertSame('%PDF', substr($response->getContent(), 0, 4));
        }
    }

    public function test_dashboard_table_filter_exports_only_the_selected_table(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $exporter = \Mockery::mock(TablePdfExporter::class)->makePartial();
        $exporter->shouldReceive('download')
            ->once()
            ->withArgs(function (string $title, string $fileName, array $sections): bool {
                $this->assertSame('Rapport du tableau de bord', $title);
                $this->assertSame('rapport-tableau-de-bord', $fileName);
                $this->assertCount(1, $sections);
                $this->assertSame('Top 5 des produits sur la période choisie', $sections[0]['title']);

                return true;
            })
            ->andReturn(response('PDF'));
        $this->app->instance(TablePdfExporter::class, $exporter);

        $this->actingAs($patron)
            ->get(route('dashboard.index', ['table' => 'top_produits', 'pdf' => 1]))
            ->assertOk();
    }

    public function test_exporter_renders_a_large_table_as_bounded_pdf_pages(): void
    {
        $rows = array_map(
            fn (int $index): array => [
                sprintf('FAC-%05d', $index),
                'Client de test '.$index,
                '1 500 FCFA',
                $index % 2 === 0 ? 'Espèces' : 'Mobile Money',
                $index % 10 === 0 ? 'Annulée' : 'Payée',
                'Vendeur de test',
                '29/09/2026 16:59',
            ],
            range(1, 500),
        );

        $response = app(TablePdfExporter::class)->download('Liste des factures', 'factures', [[
            'title' => 'Factures (500)',
            'headers' => ['Numéro', 'Client', 'Montant total', 'Paiement', 'Statut', 'Vendeur', 'Date'],
            'rows' => $rows,
        ]]);

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertSame('%PDF', substr($response->getContent(), 0, 4));
        $this->assertGreaterThan(10_000, strlen($response->getContent()));
    }

    public function test_oversized_pdf_export_requests_narrower_filters_instead_of_timing_out(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);
        $productQueries = [];

        for ($index = 1; $index <= 501; $index++) {
            Produit::create([
                'nom' => sprintf('Produit %03d', $index),
                'prix_achat' => 100,
                'prix_vente' => 150,
                'quantite_stock' => 5,
                'seuil_alerte' => 1,
            ]);
        }

        DB::listen(function (QueryExecuted $query) use (&$productQueries): void {
            if (str_contains(strtolower($query->sql), 'produits')) {
                $productQueries[] = strtolower($query->sql);
            }
        });

        $this->actingAs($employee)
            ->get(route('produits.index', ['pdf' => 1]))
            ->assertSessionHasErrors([
                'pdf' => 'Cet export dépasse 500 lignes. Réduisez la période ou ajoutez des filtres, puis exportez à nouveau.',
            ]);

        $this->assertNotEmpty($productQueries);
        $this->assertSame(
            [],
            array_filter($productQueries, fn (string $sql): bool => ! str_contains($sql, 'count(')),
        );
    }

    public function test_oversized_dashboard_export_counts_alert_rows_and_fixed_sections_before_loading_them(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $productQueries = [];

        for ($index = 1; $index <= 499; $index++) {
            Produit::create([
                'nom' => sprintf('Produit alerte %03d', $index),
                'prix_achat' => 100,
                'prix_vente' => 150,
                'quantite_stock' => 0,
                'seuil_alerte' => 1,
            ]);
        }

        DB::listen(function (QueryExecuted $query) use (&$productQueries): void {
            if (str_contains(strtolower($query->sql), 'produits')) {
                $productQueries[] = strtolower($query->sql);
            }
        });

        $this->actingAs($patron)
            ->get(route('dashboard.index', ['pdf' => 1]))
            ->assertSessionHasErrors([
                'pdf' => 'Cet export dépasse 500 lignes. Réduisez la période ou ajoutez des filtres, puis exportez à nouveau.',
            ]);

        $this->assertNotEmpty($productQueries);
        $this->assertSame(
            [],
            array_filter($productQueries, fn (string $sql): bool => ! str_contains($sql, 'count(')),
        );
    }

    public function test_employee_cannot_export_patron_only_dashboard_report(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);

        $this->actingAs($employee)
            ->get(route('dashboard.index', ['pdf' => 1]))
            ->assertForbidden();
    }
}
