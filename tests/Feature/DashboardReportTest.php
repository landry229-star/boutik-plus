<?php

namespace Tests\Feature;

use App\Models\Facture;
use App\Models\Produit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_report_filters_paid_sales_by_period_and_payment_channel(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $product = $this->createProduct();
        $this->createInvoice($product, 'FAC-PERIOD1', 'mobile_money', 2, now()->subDays(2));
        $this->createInvoice($product, 'FAC-PERIOD2', 'especes', 1, now()->subDay());
        $this->createInvoice($product, 'FAC-OUTSIDE', 'especes', 4, now()->subDays(5));

        $response = $this->actingAs($patron)->get(route('dashboard.index', [
            'date_debut' => now()->subDays(3)->toDateString(),
            'date_fin' => now()->toDateString(),
        ]));

        $response->assertOk()
            ->assertSee('4 500 FCFA')
            ->assertSee('Mobile Money')
            ->assertSee('3 000 FCFA')
            ->assertViewHas('topProduits', fn ($topProduits) => (int) $topProduits->first()->total_quantite === 3);
    }

    public function test_dashboard_rejects_a_period_where_start_is_after_end(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);

        $this->actingAs($patron)->get(route('dashboard.index', [
            'date_debut' => '2026-09-20',
            'date_fin' => '2026-09-10',
        ]))->assertSessionHasErrors('date_debut');
    }

    public function test_product_list_is_paginated(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);

        for ($index = 1; $index <= 26; $index++) {
            Produit::create([
                'nom' => sprintf('Produit %02d', $index),
                'prix_achat' => 100,
                'prix_vente' => 150,
                'quantite_stock' => 5,
                'seuil_alerte' => 1,
            ]);
        }

        $this->actingAs($employee)
            ->get(route('produits.index'))
            ->assertOk()
            ->assertSee('Produit 01')
            ->assertDontSee('Produit 26')
            ->assertSee('produits?page=2')
            ->assertSee('class="page-link"', false);
    }

    private function createProduct(): Produit
    {
        return Produit::create([
            'nom' => 'Riz local',
            'prix_achat' => 1000,
            'prix_vente' => 1500,
            'quantite_stock' => 20,
            'seuil_alerte' => 2,
        ]);
    }

    private function createInvoice(Produit $product, string $number, string $paymentMethod, int $quantity, Carbon $date): Facture
    {
        $invoice = Facture::create([
            'numero' => $number,
            'mode_paiement' => $paymentMethod,
            'montant_total' => 1500 * $quantity,
            'statut' => 'payee',
        ]);
        $invoice->forceFill(['created_at' => $date, 'updated_at' => $date])->save();
        $invoice->lignes()->create([
            'produit_id' => $product->id,
            'quantite' => $quantity,
            'prix_unitaire' => 1500,
            'sous_total' => 1500 * $quantity,
        ]);

        return $invoice;
    }
}
