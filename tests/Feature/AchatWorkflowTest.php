<?php

namespace Tests\Feature;

use App\Models\Achat;
use App\Models\Fournisseur;
use App\Models\MouvementCaisse;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchatWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_payment_is_recorded_in_the_selected_cash_channel(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $supplier = Fournisseur::create(['nom' => 'Grossiste']);
        $product = $this->createProduct();
        $purchase = Achat::create([
            'fournisseur_id' => $supplier->id,
            'user_id' => $patron->id,
            'montant_total' => 5000,
            'date_achat' => now()->toDateString(),
        ]);
        $purchase->lignes()->create([
            'produit_id' => $product->id,
            'quantite' => 5,
            'prix_achat_unitaire' => 1000,
        ]);

        $response = $this->actingAs($patron)->post(route('achats.paiement', $purchase), [
            'montant' => 2000,
            'moyen_paiement' => 'mobile_money',
        ]);

        $response->assertRedirect(route('achats.show', $purchase));
        $this->assertDatabaseHas('mouvements_caisse', [
            'achat_id' => $purchase->id,
            'user_id' => $patron->id,
            'moyen_paiement' => 'mobile_money',
            'type' => 'sortie',
            'montant' => 2000,
        ]);

        $this->actingAs($patron)->post(route('achats.paiement', $purchase), [
            'montant' => 3001,
            'moyen_paiement' => 'especes',
        ])->assertSessionHasErrors('montant');

        $this->assertDatabaseCount('mouvements_caisse', 1);
    }

    public function test_supplier_archive_preserves_its_purchase_history(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $supplier = Fournisseur::create(['nom' => 'Grossiste']);
        $product = $this->createProduct();
        $purchase = Achat::create([
            'fournisseur_id' => $supplier->id,
            'user_id' => $patron->id,
            'montant_total' => 1000,
            'date_achat' => now()->toDateString(),
        ]);
        $purchase->lignes()->create([
            'produit_id' => $product->id,
            'quantite' => 1,
            'prix_achat_unitaire' => 1000,
        ]);

        $this->actingAs($patron)
            ->delete(route('fournisseurs.destroy', $supplier))
            ->assertRedirect(route('fournisseurs.index'));

        $this->assertDatabaseHas('fournisseurs', ['id' => $supplier->id, 'actif' => false]);
        $this->assertDatabaseHas('achats', ['id' => $purchase->id, 'fournisseur_id' => $supplier->id]);
        $this->assertDatabaseHas('achat_lignes', ['achat_id' => $purchase->id]);
    }

    public function test_purchase_creation_updates_stock_and_records_the_supplier_and_stock_movement(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $supplier = Fournisseur::create(['nom' => 'Grossiste local']);
        $product = $this->createProduct();

        $response = $this->actingAs($patron)->post(route('achats.store'), [
            'fournisseur_id' => $supplier->id,
            'date_achat' => now()->toDateString(),
            'produits' => [
                [
                    'produit_id' => $product->id,
                    'quantite' => 4,
                    'prix_achat_unitaire' => 900,
                ],
            ],
        ]);

        $response->assertRedirect(route('achats.index'));
        $this->assertDatabaseHas('achats', [
            'fournisseur_id' => $supplier->id,
            'user_id' => $patron->id,
            'montant_total' => 3600,
        ]);
        $this->assertDatabaseHas('achat_lignes', [
            'produit_id' => $product->id,
            'quantite' => 4,
            'prix_achat_unitaire' => 900,
        ]);
        $this->assertDatabaseHas('produits', [
            'id' => $product->id,
            'quantite_stock' => 9,
            'prix_achat' => 900,
        ]);
        $this->assertDatabaseHas('mouvements_stock', [
            'produit_id' => $product->id,
            'user_id' => $patron->id,
            'type' => 'entree',
            'quantite' => 4,
            'motif' => 'Achat fournisseur',
        ]);
    }

    public function test_patron_can_create_and_update_a_supplier(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);

        $this->actingAs($patron)
            ->post(route('fournisseurs.store'), [
                'nom' => 'Premier nom',
                'telephone' => '97000000',
            ])
            ->assertRedirect(route('fournisseurs.index'));

        $supplier = Fournisseur::query()->where('nom', 'Premier nom')->sole();

        $this->assertDatabaseHas('fournisseurs', [
            'id' => $supplier->id,
            'telephone' => '97000000',
            'actif' => true,
        ]);

        $this->actingAs($patron)
            ->put(route('fournisseurs.update', $supplier), [
                'nom' => 'Nom actualisé',
                'telephone' => '96000000',
                'adresse' => 'Cotonou',
            ])
            ->assertRedirect(route('fournisseurs.index'));

        $this->assertDatabaseHas('fournisseurs', [
            'id' => $supplier->id,
            'nom' => 'Nom actualisé',
            'telephone' => '96000000',
            'adresse' => 'Cotonou',
        ]);
    }

    public function test_unpaid_purchase_cancellation_reverses_stock_and_preserves_history(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $supplier = Fournisseur::create(['nom' => 'Grossiste']);
        $product = $this->createProduct();
        $purchase = Achat::create([
            'fournisseur_id' => $supplier->id,
            'user_id' => $patron->id,
            'montant_total' => 3000,
            'date_achat' => now()->toDateString(),
        ]);
        $purchase->lignes()->create([
            'produit_id' => $product->id,
            'quantite' => 3,
            'prix_achat_unitaire' => 1000,
        ]);
        $product->increment('quantite_stock', 3);

        $this->actingAs($patron)
            ->post(route('achats.annuler', $purchase), ['motif_annulation' => 'Erreur de saisie'])
            ->assertRedirect(route('achats.show', $purchase));

        $this->assertDatabaseHas('achats', [
            'id' => $purchase->id,
            'statut' => 'annulee',
            'motif_annulation' => 'Erreur de saisie',
        ]);
        $this->assertDatabaseHas('produits', ['id' => $product->id, 'quantite_stock' => 5]);
        $this->assertDatabaseHas('mouvements_stock', [
            'achat_id' => $purchase->id,
            'type' => 'sortie',
            'quantite' => 3,
        ]);
        $this->assertDatabaseHas('achat_lignes', ['achat_id' => $purchase->id, 'produit_id' => $product->id]);

        $this->actingAs($patron)
            ->post(route('achats.annuler', $purchase), ['motif_annulation' => 'Deuxième essai'])
            ->assertSessionHasErrors('achat');

        $this->assertDatabaseHas('produits', ['id' => $product->id, 'quantite_stock' => 5]);
        $this->assertDatabaseCount('mouvements_stock', 1);
    }

    public function test_paid_purchase_cannot_be_cancelled_without_a_reconciled_refund(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $supplier = Fournisseur::create(['nom' => 'Grossiste']);
        $product = $this->createProduct();
        $purchase = Achat::create([
            'fournisseur_id' => $supplier->id,
            'user_id' => $patron->id,
            'montant_total' => 1000,
            'date_achat' => now()->toDateString(),
        ]);
        $purchase->lignes()->create([
            'produit_id' => $product->id,
            'quantite' => 1,
            'prix_achat_unitaire' => 1000,
        ]);
        MouvementCaisse::create([
            'date_jour' => now()->toDateString(),
            'moyen_paiement' => 'especes',
            'user_id' => $patron->id,
            'achat_id' => $purchase->id,
            'type' => 'sortie',
            'montant' => 500,
            'description' => 'Paiement partiel',
        ]);

        $this->actingAs($patron)
            ->post(route('achats.annuler', $purchase), ['motif_annulation' => 'Commande annulée'])
            ->assertSessionHasErrors('achat');

        $this->assertDatabaseHas('achats', ['id' => $purchase->id, 'statut' => 'enregistree']);
        $this->assertDatabaseHas('produits', ['id' => $product->id, 'quantite_stock' => 5]);
        $this->assertDatabaseCount('mouvements_stock', 0);
        $this->assertDatabaseCount('mouvements_caisse', 1);
    }

    public function test_purchase_cancellation_is_rejected_if_stock_would_become_negative(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $supplier = Fournisseur::create(['nom' => 'Grossiste']);
        $product = $this->createProduct();
        $purchase = Achat::create([
            'fournisseur_id' => $supplier->id,
            'user_id' => $patron->id,
            'montant_total' => 2000,
            'date_achat' => now()->toDateString(),
        ]);
        $purchase->lignes()->create([
            'produit_id' => $product->id,
            'quantite' => 2,
            'prix_achat_unitaire' => 1000,
        ]);
        $product->update(['quantite_stock' => 1]);

        $this->actingAs($patron)
            ->post(route('achats.annuler', $purchase), ['motif_annulation' => 'Erreur'])
            ->assertSessionHasErrors('achat');

        $this->assertDatabaseHas('achats', ['id' => $purchase->id, 'statut' => 'enregistree']);
        $this->assertDatabaseHas('produits', ['id' => $product->id, 'quantite_stock' => 1]);
        $this->assertDatabaseCount('mouvements_stock', 0);
    }

    public function test_employee_cannot_cancel_a_purchase_or_cash_movement(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $employee = User::factory()->create(['role' => 'employe']);
        $supplier = Fournisseur::create(['nom' => 'Grossiste']);
        $product = $this->createProduct();
        $purchase = Achat::create([
            'fournisseur_id' => $supplier->id,
            'user_id' => $patron->id,
            'montant_total' => 1000,
            'date_achat' => now()->toDateString(),
        ]);
        $purchase->lignes()->create([
            'produit_id' => $product->id,
            'quantite' => 1,
            'prix_achat_unitaire' => 1000,
        ]);
        $cashMovement = MouvementCaisse::create([
            'date_jour' => now()->toDateString(),
            'moyen_paiement' => 'especes',
            'user_id' => $patron->id,
            'type' => 'entree',
            'montant' => 1000,
            'description' => 'Mouvement manuel',
        ]);

        $this->actingAs($employee)
            ->post(route('achats.annuler', $purchase), ['motif_annulation' => 'Test'])
            ->assertForbidden();

        $this->actingAs($employee)
            ->post(route('caisse.annuler', $cashMovement))
            ->assertForbidden();

        $this->assertDatabaseHas('achats', ['id' => $purchase->id, 'statut' => 'enregistree']);
        $this->assertDatabaseCount('mouvements_caisse', 1);
    }

    public function test_purchase_list_filters_by_supplier_and_cancellation_status(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $supplier = Fournisseur::create(['nom' => 'Fournisseur ciblé']);
        $product = $this->createProduct();
        $purchase = Achat::create([
            'fournisseur_id' => $supplier->id,
            'user_id' => $patron->id,
            'montant_total' => 1000,
            'date_achat' => now()->toDateString(),
            'statut' => 'annulee',
        ]);
        $purchase->lignes()->create([
            'produit_id' => $product->id,
            'quantite' => 1,
            'prix_achat_unitaire' => 1000,
        ]);
        Achat::create([
            'fournisseur_id' => Fournisseur::create(['nom' => 'Autre fournisseur'])->id,
            'user_id' => $patron->id,
            'montant_total' => 2000,
            'date_achat' => now()->toDateString(),
        ]);

        $this->actingAs($patron)
            ->get(route('achats.index', ['fournisseur_id' => $supplier->id, 'statut' => 'annulee']))
            ->assertOk()
            ->assertSee('Fournisseur ciblé')
            ->assertSee(route('achats.supprimer-definitivement', $purchase))
            ->assertSee('Annulé')
            ->assertViewHas('achats', fn ($achats): bool => $achats->total() === 1
                && $achats->first()->id === $purchase->id);
    }

    public function test_supplier_archive_can_be_deleted_only_without_purchase_history(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $supplierWithoutHistory = Fournisseur::create(['nom' => 'Sans historique']);
        $supplierWithHistory = Fournisseur::create(['nom' => 'Avec historique']);
        $product = $this->createProduct();
        Achat::create([
            'fournisseur_id' => $supplierWithHistory->id,
            'user_id' => $patron->id,
            'montant_total' => 1000,
            'date_achat' => now()->toDateString(),
        ])->lignes()->create([
            'produit_id' => $product->id,
            'quantite' => 1,
            'prix_achat_unitaire' => 1000,
        ]);
        $supplierWithoutHistory->update(['actif' => false]);
        $supplierWithHistory->update(['actif' => false]);

        $this->actingAs($patron)
            ->delete(route('fournisseurs.supprimer-definitivement', $supplierWithoutHistory))
            ->assertRedirect(route('fournisseurs.index', ['statut' => 'archive']));

        $this->assertDatabaseMissing('fournisseurs', ['id' => $supplierWithoutHistory->id]);

        $this->actingAs($patron)
            ->delete(route('fournisseurs.supprimer-definitivement', $supplierWithHistory))
            ->assertSessionHasErrors('fournisseur');

        $this->assertDatabaseHas('fournisseurs', ['id' => $supplierWithHistory->id, 'actif' => false]);
    }

    public function test_patron_can_permanently_delete_a_cancelled_purchase_and_its_stock_history(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $supplier = Fournisseur::create(['nom' => 'Grossiste']);
        $product = $this->createProduct();
        $purchase = Achat::create([
            'fournisseur_id' => $supplier->id,
            'user_id' => $patron->id,
            'montant_total' => 2000,
            'date_achat' => now()->toDateString(),
            'statut' => 'annulee',
        ]);
        $purchase->lignes()->create([
            'produit_id' => $product->id,
            'quantite' => 2,
            'prix_achat_unitaire' => 1000,
        ]);
        MouvementStock::create([
            'produit_id' => $product->id,
            'user_id' => $patron->id,
            'achat_id' => $purchase->id,
            'type' => 'entree',
            'quantite' => 2,
            'motif' => 'Achat',
        ]);
        MouvementStock::create([
            'produit_id' => $product->id,
            'user_id' => $patron->id,
            'achat_id' => $purchase->id,
            'type' => 'sortie',
            'quantite' => 2,
            'motif' => 'Annulation',
        ]);

        $this->actingAs($patron)
            ->delete(route('achats.supprimer-definitivement', $purchase))
            ->assertRedirect(route('achats.index'));

        $this->assertDatabaseMissing('achats', ['id' => $purchase->id]);
        $this->assertDatabaseMissing('achat_lignes', ['achat_id' => $purchase->id]);
        $this->assertDatabaseMissing('mouvements_stock', ['achat_id' => $purchase->id]);
        $this->assertDatabaseHas('produits', ['id' => $product->id, 'quantite_stock' => 5]);
    }

    public function test_purchase_cannot_be_permanently_deleted_before_cancellation(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $supplier = Fournisseur::create(['nom' => 'Grossiste']);
        $purchase = Achat::create([
            'fournisseur_id' => $supplier->id,
            'user_id' => $patron->id,
            'montant_total' => 1000,
            'date_achat' => now()->toDateString(),
        ]);

        $this->actingAs($patron)
            ->delete(route('achats.supprimer-definitivement', $purchase))
            ->assertSessionHasErrors('achat');

        $this->assertDatabaseHas('achats', ['id' => $purchase->id, 'statut' => 'enregistree']);
    }

    private function createProduct(): Produit
    {
        return Produit::create([
            'nom' => 'Riz local',
            'prix_achat' => 1000,
            'prix_vente' => 1500,
            'quantite_stock' => 5,
            'seuil_alerte' => 2,
        ]);
    }
}
