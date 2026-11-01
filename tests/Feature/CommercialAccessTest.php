<?php

namespace Tests\Feature;

use App\Models\Facture;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_consult_stock_but_cannot_change_products_or_stock(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);
        $product = $this->createProduct(5);
        $this->assertTrue($employee->actif);

        $this->actingAs($employee)
            ->get(route('produits.index'))
            ->assertOk()
            ->assertSee($product->nom);

        $this->actingAs($employee)
            ->post(route('produits.store'), [
                'nom' => 'Produit interdit',
                'prix_achat' => 10,
                'prix_vente' => 20,
                'quantite_stock' => 10,
                'seuil_alerte' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($employee)
            ->post(route('produits.mouvement', $product), [
                'type' => 'entree',
                'quantite' => 10,
                'motif' => 'Test',
            ])
            ->assertForbidden();

        $this->actingAs($employee)
            ->delete(route('produits.supprimer-definitivement', $product))
            ->assertForbidden();

        $this->assertDatabaseHas('produits', [
            'id' => $product->id,
            'quantite_stock' => 5,
        ]);
    }

    public function test_patron_can_create_a_product_and_its_initial_stock_is_audited(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);

        $response = $this->actingAs($patron)->post(route('produits.store'), [
            'nom' => 'Huile de palme',
            'categorie' => 'Alimentation',
            'prix_achat' => 1200,
            'prix_vente' => 1800,
            'quantite_stock' => 8,
            'seuil_alerte' => 2,
        ]);

        $response->assertRedirect(route('produits.index'));
        $this->assertDatabaseHas('produits', [
            'nom' => 'Huile de palme',
            'quantite_stock' => 8,
        ]);
        $this->assertDatabaseHas('mouvements_stock', [
            'user_id' => $patron->id,
            'type' => 'entree',
            'quantite' => 8,
            'motif' => 'Stock initial',
        ]);
    }

    public function test_patron_can_adjust_product_stock_and_the_adjustment_is_audited(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $product = $this->createProduct(5);

        $response = $this->actingAs($patron)->post(route('produits.mouvement', $product), [
            'type' => 'sortie',
            'quantite' => 2,
            'motif' => 'Casse',
        ]);

        $response->assertRedirect(route('produits.index'));
        $this->assertDatabaseHas('produits', [
            'id' => $product->id,
            'quantite_stock' => 3,
        ]);
        $this->assertDatabaseHas('mouvements_stock', [
            'produit_id' => $product->id,
            'user_id' => $patron->id,
            'type' => 'sortie',
            'quantite' => 2,
            'motif' => 'Casse',
        ]);
    }

    public function test_product_list_filters_search_status_and_stock_level(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $lowStockProduct = $this->createProduct(1);
        $lowStockProduct->update(['nom' => 'Farine alerte']);
        $archivedProduct = $this->createProduct(0);
        $archivedProduct->update(['nom' => 'Farine archivee', 'actif' => false]);

        $this->actingAs($patron)
            ->get(route('produits.index', ['q' => 'Farine', 'statut' => 'actif', 'stock' => 'alerte']))
            ->assertOk()
            ->assertSee('Farine alerte')
            ->assertDontSee('Farine archivee');
    }

    public function test_archived_product_can_be_deleted_only_without_stock_or_history(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $emptyArchivedProduct = $this->createProduct(0);
        $emptyArchivedProduct->update(['actif' => false]);
        $productWithStock = $this->createProduct(2);
        $productWithStock->update(['actif' => false]);
        $productWithMovement = $this->createProduct(0);
        $productWithMovement->update(['actif' => false]);
        MouvementStock::create([
            'produit_id' => $productWithMovement->id,
            'user_id' => $patron->id,
            'type' => 'entree',
            'quantite' => 1,
            'motif' => 'Historique de test',
        ]);

        $this->actingAs($patron)
            ->delete(route('produits.supprimer-definitivement', $emptyArchivedProduct))
            ->assertRedirect(route('produits.index', ['statut' => 'archive']));

        $this->assertDatabaseMissing('produits', ['id' => $emptyArchivedProduct->id]);

        $this->actingAs($patron)
            ->delete(route('produits.supprimer-definitivement', $productWithStock))
            ->assertSessionHasErrors('produit');

        $this->assertDatabaseHas('produits', ['id' => $productWithStock->id, 'actif' => false, 'quantite_stock' => 2]);

        $this->actingAs($patron)
            ->delete(route('produits.supprimer-definitivement', $productWithMovement))
            ->assertSessionHasErrors('produit');

        $this->assertDatabaseHas('produits', ['id' => $productWithMovement->id, 'actif' => false]);
    }

    public function test_employee_is_forbidden_from_patron_only_modules(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);

        foreach ([
            'dashboard.index',
            'caisse.index',
            'fournisseurs.index',
            'achats.index',
            'utilisateurs.index',
        ] as $routeName) {
            $this->actingAs($employee)
                ->get(route($routeName))
                ->assertForbidden();
        }
    }

    public function test_patron_can_create_and_deactivate_an_employee_account(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);

        $response = $this->actingAs($patron)->post(route('utilisateurs.store'), [
            'name' => 'Employé de caisse',
            'email' => 'caisse@example.test',
            'password' => 'secure-test-password',
        ]);

        $response->assertRedirect(route('utilisateurs.index'));
        $this->assertDatabaseHas('users', [
            'name' => 'Employé de caisse',
            'email' => 'caisse@example.test',
            'role' => 'employe',
            'actif' => true,
        ]);

        $employee = User::query()->where('email', 'caisse@example.test')->sole();

        $this->actingAs($patron)
            ->patch(route('utilisateurs.statut', $employee), ['actif' => false])
            ->assertRedirect(route('utilisateurs.index'));

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'actif' => false,
        ]);
    }

    public function test_inactive_employee_cannot_log_in(): void
    {
        $employee = User::factory()->create([
            'role' => 'employe',
            'actif' => false,
            'password' => 'password-for-test',
        ]);

        $this->post(route('login.store'), [
            'email' => $employee->email,
            'password' => 'password-for-test',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivated_employee_session_is_terminated_on_the_next_request(): void
    {
        $employee = User::factory()->create([
            'role' => 'employe',
            'actif' => false,
        ]);

        $this->actingAs($employee)
            ->get(route('produits.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_patron_accounts_cannot_be_disabled_from_employee_management(): void
    {
        $currentPatron = User::factory()->create(['role' => 'patron']);
        $anotherPatron = User::factory()->create(['role' => 'patron']);

        $this->actingAs($currentPatron)
            ->patch(route('utilisateurs.statut', $anotherPatron), ['actif' => false])
            ->assertSessionHasErrors('utilisateur');

        $this->assertDatabaseHas('users', ['id' => $anotherPatron->id, 'actif' => true]);
    }

    public function test_patron_can_update_and_permanently_delete_an_archived_employee_without_history(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $employee = User::factory()->create(['role' => 'employe']);

        $this->actingAs($patron)
            ->patch(route('utilisateurs.update', $employee), [
                'name' => 'Employé modifié',
                'email' => 'modifie@example.test',
            ])
            ->assertRedirect(route('utilisateurs.index'));

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'name' => 'Employé modifié',
            'email' => 'modifie@example.test',
        ]);

        $this->actingAs($patron)
            ->patch(route('utilisateurs.statut', $employee), ['actif' => false])
            ->assertRedirect(route('utilisateurs.index'));

        $this->actingAs($patron)
            ->delete(route('utilisateurs.supprimer-definitivement', $employee))
            ->assertRedirect(route('utilisateurs.index', ['statut' => 'archive']));

        $this->assertDatabaseMissing('users', ['id' => $employee->id]);
    }

    public function test_employee_account_with_business_history_cannot_be_permanently_deleted(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $employee = User::factory()->create(['role' => 'employe', 'actif' => false]);
        Facture::create([
            'user_id' => $employee->id,
            'numero' => 'FAC-HISTOIRE1',
            'mode_paiement' => 'especes',
            'montant_total' => 0,
        ]);

        $this->actingAs($patron)
            ->delete(route('utilisateurs.supprimer-definitivement', $employee))
            ->assertSessionHasErrors('utilisateur');

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'actif' => false]);
    }

    public function test_employee_list_filters_name_access_status_and_role(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $matchingEmployee = User::factory()->create([
            'name' => 'Employé recherché',
            'email' => 'recherche@example.test',
            'role' => 'employe',
            'actif' => false,
        ]);
        User::factory()->create([
            'name' => 'Employé actif',
            'email' => 'actif@example.test',
            'role' => 'employe',
            'actif' => true,
        ]);

        $this->actingAs($patron)
            ->get(route('utilisateurs.index', ['q' => 'recherché', 'statut' => 'archive', 'role' => 'employe']))
            ->assertOk()
            ->assertSee($matchingEmployee->email)
            ->assertDontSee('actif@example.test');
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
