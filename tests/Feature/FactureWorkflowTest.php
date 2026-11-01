<?php

namespace Tests\Feature;

use App\Models\Facture;
use App\Models\MouvementCaisse;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FactureWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_invoice_lines_are_aggregated_and_mobile_money_is_tracked_separately(): void
    {
        config()->set('boutik.tax.enabled', false);

        $employee = User::factory()->create(['role' => 'employe']);
        $product = $this->createProduct(5);

        $response = $this->actingAs($employee)->post(route('factures.store'), [
            'mode_paiement' => 'mobile_money',
            'produits' => [
                ['id' => $product->id, 'quantite' => 2],
                ['id' => $product->id, 'quantite' => 2],
            ],
        ]);

        $invoice = Facture::query()->sole();

        $response->assertRedirect(route('factures.show', $invoice));
        $this->assertDatabaseHas('produits', ['id' => $product->id, 'quantite_stock' => 1]);
        $this->assertDatabaseHas('facture_lignes', [
            'facture_id' => $invoice->id,
            'produit_id' => $product->id,
            'quantite' => 4,
            'prix_unitaire' => 1500,
            'sous_total' => 6000,
        ]);
        $this->assertDatabaseHas('factures', [
            'id' => $invoice->id,
            'montant_ht' => 6000,
            'montant_taxe' => 0,
            'taux_taxe' => 0,
            'montant_total' => 6000,
        ]);
        $this->assertDatabaseHas('mouvements_caisse', [
            'facture_id' => $invoice->id,
            'moyen_paiement' => 'mobile_money',
            'type' => 'entree',
            'montant' => 6000,
        ]);
        $this->assertDatabaseHas('mouvements_stock', [
            'facture_id' => $invoice->id,
            'user_id' => $employee->id,
            'type' => 'sortie',
            'quantite' => 4,
        ]);
    }

    public function test_duplicate_invoice_quantities_cannot_exceed_stock(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);
        $product = $this->createProduct(3);

        $this->actingAs($employee)->post(route('factures.store'), [
            'mode_paiement' => 'especes',
            'produits' => [
                ['id' => $product->id, 'quantite' => 2],
                ['id' => $product->id, 'quantite' => 2],
            ],
        ])->assertSessionHasErrors('produits');

        $this->assertDatabaseCount('factures', 0);
        $this->assertDatabaseHas('produits', ['id' => $product->id, 'quantite_stock' => 3]);
        $this->assertDatabaseCount('mouvements_caisse', 0);
    }

    public function test_configured_tax_is_snapshotted_and_included_in_invoice_and_cash_totals(): void
    {
        config()->set('boutik.tax.enabled', true);
        config()->set('boutik.tax.rate', 10);
        config()->set('boutik.tax.label', 'Taxe de test');

        $employee = User::factory()->create(['role' => 'employe']);
        $product = $this->createProduct(2);

        $response = $this->actingAs($employee)->post(route('factures.store'), [
            'mode_paiement' => 'especes',
            'produits' => [
                ['id' => $product->id, 'quantite' => 1],
            ],
        ]);

        $invoice = Facture::query()->sole();

        $response->assertRedirect(route('factures.show', $invoice));
        $this->assertDatabaseHas('factures', [
            'id' => $invoice->id,
            'montant_ht' => 1500,
            'montant_taxe' => 150,
            'taux_taxe' => 10,
            'libelle_taxe' => 'Taxe de test',
            'montant_total' => 1650,
        ]);
        $this->assertDatabaseHas('mouvements_caisse', [
            'facture_id' => $invoice->id,
            'type' => 'entree',
            'montant' => 1650,
        ]);
    }

    public function test_invoice_cancellation_reverses_stock_and_payment_once(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $employee = User::factory()->create(['role' => 'employe']);
        $product = $this->createProduct(5);
        $invoice = Facture::create([
            'user_id' => $employee->id,
            'numero' => 'FAC-TEST123',
            'mode_paiement' => 'mobile_money',
            'montant_total' => 3000,
            'statut' => 'payee',
        ]);
        $invoice->lignes()->create([
            'produit_id' => $product->id,
            'quantite' => 2,
            'prix_unitaire' => 1500,
            'sous_total' => 3000,
        ]);
        MouvementCaisse::create([
            'date_jour' => now()->toDateString(),
            'moyen_paiement' => 'mobile_money',
            'user_id' => $employee->id,
            'facture_id' => $invoice->id,
            'type' => 'entree',
            'montant' => 3000,
            'description' => 'Vente facture FAC-TEST123',
        ]);
        $product->decrement('quantite_stock', 2);

        $this->actingAs($employee)
            ->post(route('factures.annuler', $invoice), ['motif_annulation' => 'Test'])
            ->assertForbidden();

        $this->actingAs($patron)
            ->post(route('factures.annuler', $invoice), ['motif_annulation' => 'Erreur de saisie'])
            ->assertRedirect(route('factures.show', $invoice));

        $this->assertDatabaseHas('factures', ['id' => $invoice->id, 'statut' => 'annulee']);
        $this->assertDatabaseHas('produits', ['id' => $product->id, 'quantite_stock' => 5]);
        $this->assertDatabaseHas('mouvements_caisse', [
            'facture_id' => $invoice->id,
            'moyen_paiement' => 'mobile_money',
            'type' => 'sortie',
            'montant' => 3000,
        ]);
        $this->assertDatabaseHas('mouvements_stock', [
            'facture_id' => $invoice->id,
            'user_id' => $patron->id,
            'type' => 'entree',
            'quantite' => 2,
        ]);

        $this->actingAs($patron)
            ->post(route('factures.annuler', $invoice), ['motif_annulation' => 'Deuxième essai'])
            ->assertSessionHasErrors('facture');

        $this->assertDatabaseCount('mouvements_caisse', 2);
        $this->assertDatabaseHas('produits', ['id' => $product->id, 'quantite_stock' => 5]);
    }

    public function test_employee_cannot_cancel_invoice(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);
        $invoice = Facture::create([
            'numero' => 'FAC-TEST456',
            'mode_paiement' => 'especes',
            'montant_total' => 0,
            'statut' => 'payee',
        ]);

        $this->actingAs($employee)
            ->post(route('factures.annuler', $invoice), ['motif_annulation' => 'Test'])
            ->assertForbidden();

        $this->assertDatabaseHas('factures', ['id' => $invoice->id, 'statut' => 'payee']);
    }

    public function test_invoice_list_filters_by_search_status_payment_and_date(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $matchingInvoice = Facture::create([
            'numero' => 'FAC-FILTRE1',
            'client_nom' => 'Client recherche',
            'mode_paiement' => 'mobile_money',
            'montant_total' => 1000,
            'statut' => 'payee',
        ]);
        Facture::create([
            'numero' => 'FAC-FILTRE2',
            'client_nom' => 'Autre client',
            'mode_paiement' => 'especes',
            'montant_total' => 2000,
            'statut' => 'annulee',
        ]);

        $this->actingAs($patron)
            ->get(route('factures.index', [
                'q' => 'recherche',
                'statut' => 'payee',
                'moyen_paiement' => 'mobile_money',
                'date_debut' => now()->toDateString(),
                'date_fin' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee($matchingInvoice->numero)
            ->assertDontSee('FAC-FILTRE2');
    }

    public function test_invoice_list_shows_cancellation_action_only_for_paid_invoices_to_patron(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $employee = User::factory()->create(['role' => 'employe']);
        $paidInvoice = Facture::create([
            'numero' => 'FAC-ACTION01',
            'mode_paiement' => 'especes',
            'montant_total' => 1000,
            'statut' => 'payee',
        ]);
        $cancelledInvoice = Facture::create([
            'numero' => 'FAC-ACTION02',
            'mode_paiement' => 'especes',
            'montant_total' => 1000,
            'statut' => 'annulee',
        ]);

        $this->actingAs($patron)
            ->get(route('factures.index'))
            ->assertOk()
            ->assertSee('Actions')
            ->assertSee(route('factures.show', $paidInvoice).'#motif_annulation')
            ->assertDontSee(route('factures.supprimer-definitivement', $paidInvoice))
            ->assertSee(route('factures.supprimer-definitivement', $cancelledInvoice))
            ->assertDontSee(route('factures.show', $cancelledInvoice).'#motif_annulation');

        $this->actingAs($employee)
            ->get(route('factures.index'))
            ->assertOk()
            ->assertDontSee(route('factures.show', $paidInvoice).'#motif_annulation')
            ->assertDontSee(route('factures.supprimer-definitivement', $cancelledInvoice));
    }

    public function test_patron_can_permanently_delete_a_cancelled_invoice_and_its_linked_records(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $product = $this->createProduct(5);
        $invoice = Facture::create([
            'user_id' => $patron->id,
            'numero' => 'FAC-SUPPR01',
            'mode_paiement' => 'mobile_money',
            'montant_total' => 3000,
            'statut' => 'annulee',
        ]);
        $invoice->lignes()->create([
            'produit_id' => $product->id,
            'quantite' => 2,
            'prix_unitaire' => 1500,
            'sous_total' => 3000,
        ]);
        MouvementCaisse::create([
            'date_jour' => now()->toDateString(),
            'moyen_paiement' => 'mobile_money',
            'user_id' => $patron->id,
            'facture_id' => $invoice->id,
            'type' => 'entree',
            'montant' => 3000,
            'description' => 'Vente annulée',
        ]);
        MouvementCaisse::create([
            'date_jour' => now()->toDateString(),
            'moyen_paiement' => 'mobile_money',
            'user_id' => $patron->id,
            'facture_id' => $invoice->id,
            'type' => 'sortie',
            'montant' => 3000,
            'description' => 'Annulation vente',
        ]);
        MouvementStock::create([
            'produit_id' => $product->id,
            'user_id' => $patron->id,
            'facture_id' => $invoice->id,
            'type' => 'sortie',
            'quantite' => 2,
            'motif' => 'Vente',
        ]);
        MouvementStock::create([
            'produit_id' => $product->id,
            'user_id' => $patron->id,
            'facture_id' => $invoice->id,
            'type' => 'entree',
            'quantite' => 2,
            'motif' => 'Annulation',
        ]);
        DB::table('mobile_money_payments')->insert([
            'facture_id' => $invoice->id,
            'provider' => 'mtn',
            'status' => 'failed',
            'reference' => 'MM-SUPPR01',
            'montant' => 3000,
            'devise' => 'XOF',
            'operation' => 'collect',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($patron)
            ->delete(route('factures.supprimer-definitivement', $invoice))
            ->assertRedirect(route('factures.index'));

        $this->assertDatabaseMissing('factures', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('facture_lignes', ['facture_id' => $invoice->id]);
        $this->assertDatabaseMissing('mouvements_caisse', ['facture_id' => $invoice->id]);
        $this->assertDatabaseMissing('mouvements_stock', ['facture_id' => $invoice->id]);
        $this->assertDatabaseMissing('mobile_money_payments', ['reference' => 'MM-SUPPR01']);
        $this->assertDatabaseHas('produits', ['id' => $product->id, 'quantite_stock' => 5]);
    }

    public function test_paid_invoice_cannot_be_permanently_deleted(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $invoice = Facture::create([
            'numero' => 'FAC-SUPPR02',
            'mode_paiement' => 'especes',
            'montant_total' => 1000,
            'statut' => 'payee',
        ]);

        $this->actingAs($patron)
            ->delete(route('factures.supprimer-definitivement', $invoice))
            ->assertSessionHasErrors('facture');

        $this->assertDatabaseHas('factures', ['id' => $invoice->id, 'statut' => 'payee']);
    }

    private function createProduct(int $stock): Produit
    {
        return Produit::create([
            'nom' => 'Riz local',
            'prix_achat' => 1000,
            'prix_vente' => 1500,
            'quantite_stock' => $stock,
            'seuil_alerte' => 2,
        ]);
    }
}
