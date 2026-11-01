<?php

namespace Tests\Feature;

use App\Models\Facture;
use App\Models\MouvementCaisse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MouvementCaisseTest extends TestCase
{
    use RefreshDatabase;

    public function test_mouvement_caisse_uses_the_migrated_table(): void
    {
        $mouvement = MouvementCaisse::create([
            'date_jour' => now()->toDateString(),
            'type' => 'entree',
            'montant' => 1500,
            'description' => 'Test de caisse',
        ]);

        $this->assertDatabaseHas('mouvements_caisse', [
            'id' => $mouvement->id,
            'description' => 'Test de caisse',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'patron']))
            ->get(route('caisse.index'))
            ->assertOk();
    }

    public function test_cashier_totals_separate_cash_from_mobile_money(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $today = now()->toDateString();

        MouvementCaisse::create([
            'date_jour' => $today,
            'moyen_paiement' => 'especes',
            'user_id' => $patron->id,
            'type' => 'entree',
            'montant' => 4000,
            'description' => 'Vente espèces',
        ]);
        MouvementCaisse::create([
            'date_jour' => $today,
            'moyen_paiement' => 'especes',
            'user_id' => $patron->id,
            'type' => 'sortie',
            'montant' => 500,
            'description' => 'Petite dépense',
        ]);
        MouvementCaisse::create([
            'date_jour' => $today,
            'moyen_paiement' => 'mobile_money',
            'user_id' => $patron->id,
            'type' => 'entree',
            'montant' => 2000,
            'description' => 'Vente Mobile Money',
        ]);
        MouvementCaisse::create([
            'date_jour' => $today,
            'moyen_paiement' => 'mobile_money',
            'user_id' => $patron->id,
            'type' => 'sortie',
            'montant' => 250,
            'description' => 'Remboursement Mobile Money',
        ]);

        $this->actingAs($patron)
            ->get(route('caisse.index'))
            ->assertViewHas('solde', 3500.0)
            ->assertViewHas('totalMobileMoney', 1750.0);
    }

    public function test_patron_can_record_a_cash_movement_with_a_description(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);

        $response = $this->actingAs($patron)->post(route('caisse.store'), [
            'type' => 'sortie',
            'moyen_paiement' => 'especes',
            'montant' => 1250,
            'description' => 'Transport fournisseur',
        ]);

        $response->assertRedirect(route('caisse.index'));
        $this->assertDatabaseHas('mouvements_caisse', [
            'user_id' => $patron->id,
            'type' => 'sortie',
            'moyen_paiement' => 'especes',
            'montant' => 1250,
            'description' => 'Transport fournisseur',
        ]);
    }

    public function test_manual_cash_movement_cancellation_creates_one_inverse_entry(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $movement = MouvementCaisse::create([
            'date_jour' => now()->toDateString(),
            'moyen_paiement' => 'especes',
            'user_id' => $patron->id,
            'type' => 'entree',
            'montant' => 1250,
            'description' => 'Saisie incorrecte',
        ]);

        $this->actingAs($patron)
            ->post(route('caisse.annuler', $movement))
            ->assertRedirect(route('caisse.index', ['date' => $movement->date_jour]));

        $this->assertDatabaseHas('mouvements_caisse', [
            'mouvement_origine_id' => $movement->id,
            'type' => 'sortie',
            'montant' => 1250,
            'moyen_paiement' => 'especes',
        ]);

        $this->actingAs($patron)
            ->post(route('caisse.annuler', $movement))
            ->assertSessionHasErrors('mouvement');

        $this->assertDatabaseCount('mouvements_caisse', 2);
    }

    public function test_cash_movement_filtering_restricts_the_list_without_changing_daily_totals(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        MouvementCaisse::create([
            'date_jour' => now()->toDateString(),
            'moyen_paiement' => 'mobile_money',
            'user_id' => $patron->id,
            'type' => 'entree',
            'montant' => 2000,
            'description' => 'Vente mobile',
        ]);
        MouvementCaisse::create([
            'date_jour' => now()->toDateString(),
            'moyen_paiement' => 'especes',
            'user_id' => $patron->id,
            'type' => 'sortie',
            'montant' => 500,
            'description' => 'Transport',
        ]);

        $this->actingAs($patron)
            ->get(route('caisse.index', ['type' => 'entree', 'moyen_paiement' => 'mobile_money', 'q' => 'Vente']))
            ->assertOk()
            ->assertViewHas('mouvements', function ($mouvements): bool {
                return $mouvements->total() === 1
                    && $mouvements->items()[0]->description === 'Vente mobile';
            })
            ->assertViewHas('totalMobileMoney', 2000.0);
    }

    public function test_invoice_linked_cash_movement_cannot_be_cancelled_from_the_cash_journal(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $invoice = Facture::create([
            'user_id' => $patron->id,
            'numero' => 'FAC-LIEE123',
            'mode_paiement' => 'especes',
            'montant_total' => 1000,
        ]);
        $movement = MouvementCaisse::create([
            'date_jour' => now()->toDateString(),
            'moyen_paiement' => 'especes',
            'user_id' => $patron->id,
            'facture_id' => $invoice->id,
            'type' => 'entree',
            'montant' => 1000,
            'description' => 'Vente facture FAC-LIEE123',
        ]);

        $this->actingAs($patron)
            ->post(route('caisse.annuler', $movement))
            ->assertSessionHasErrors('mouvement');

        $this->assertDatabaseCount('mouvements_caisse', 1);
    }

    public function test_patron_can_permanently_delete_a_cancelled_manual_cash_movement_pair(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $movement = MouvementCaisse::create([
            'date_jour' => now()->toDateString(),
            'moyen_paiement' => 'especes',
            'user_id' => $patron->id,
            'type' => 'entree',
            'montant' => 1250,
            'description' => 'Saisie annulée',
        ]);
        $cancellation = MouvementCaisse::create([
            'date_jour' => now()->toDateString(),
            'moyen_paiement' => 'especes',
            'user_id' => $patron->id,
            'mouvement_origine_id' => $movement->id,
            'type' => 'sortie',
            'montant' => 1250,
            'description' => 'Annulation',
        ]);

        $this->actingAs($patron)
            ->get(route('caisse.index', ['date' => $movement->date_jour]))
            ->assertOk()
            ->assertSee(route('caisse.supprimer-definitivement', $movement));

        $this->actingAs($patron)
            ->delete(route('caisse.supprimer-definitivement', $movement))
            ->assertRedirect(route('caisse.index', ['date' => $movement->date_jour]));

        $this->assertDatabaseMissing('mouvements_caisse', ['id' => $movement->id]);
        $this->assertDatabaseMissing('mouvements_caisse', ['id' => $cancellation->id]);
    }

    public function test_manual_cash_movement_cannot_be_deleted_until_it_is_cancelled(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $movement = MouvementCaisse::create([
            'date_jour' => now()->toDateString(),
            'moyen_paiement' => 'especes',
            'user_id' => $patron->id,
            'type' => 'entree',
            'montant' => 1250,
            'description' => 'Mouvement non annulé',
        ]);

        $this->actingAs($patron)
            ->delete(route('caisse.supprimer-definitivement', $movement))
            ->assertSessionHasErrors('mouvement');

        $this->assertDatabaseHas('mouvements_caisse', ['id' => $movement->id]);
    }
}
