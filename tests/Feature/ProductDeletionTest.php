<?php

namespace Tests\Feature;

use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_patron_can_permanently_delete_an_archived_product_without_stock_or_history(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $product = $this->createProduct();
        $product->update(['actif' => false]);

        $this->actingAs($patron)
            ->get(route('produits.index', ['statut' => 'archive']))
            ->assertOk()
            ->assertSee(route('produits.supprimer-definitivement', $product));

        $this->actingAs($patron)
            ->delete(route('produits.supprimer-definitivement', $product))
            ->assertRedirect(route('produits.index', ['statut' => 'archive']));

        $this->assertDatabaseMissing('produits', ['id' => $product->id]);
    }

    public function test_product_with_stock_history_cannot_be_permanently_deleted(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $product = $this->createProduct();
        $product->update(['actif' => false]);
        MouvementStock::create([
            'produit_id' => $product->id,
            'user_id' => $patron->id,
            'type' => 'entree',
            'quantite' => 5,
            'motif' => 'Stock initial',
        ]);

        $this->actingAs($patron)
            ->delete(route('produits.supprimer-definitivement', $product))
            ->assertSessionHasErrors('produit');

        $this->assertDatabaseHas('produits', ['id' => $product->id, 'actif' => false]);
        $this->assertDatabaseHas('mouvements_stock', ['produit_id' => $product->id]);
    }

    public function test_active_product_cannot_be_permanently_deleted_even_without_history(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $product = $this->createProduct();

        $this->actingAs($patron)
            ->delete(route('produits.supprimer-definitivement', $product))
            ->assertSessionHasErrors('produit');

        $this->assertDatabaseHas('produits', ['id' => $product->id, 'actif' => true]);
    }

    private function createProduct(): Produit
    {
        return Produit::create([
            'nom' => 'Produit de test',
            'prix_achat' => 1000,
            'prix_vente' => 1500,
            'quantite_stock' => 0,
            'seuil_alerte' => 2,
        ]);
    }
}
