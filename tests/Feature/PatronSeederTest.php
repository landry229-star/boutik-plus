<?php

namespace Tests\Feature;

use App\Models\Achat;
use App\Models\Facture;
use App\Models\Fournisseur;
use App\Models\MouvementCaisse;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\User;
use Database\Seeders\PatronSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class PatronSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_recreating_patron_preserves_business_history_and_actor_names(): void
    {
        $oldPatron = User::factory()->create([
            'name' => 'Ancien patron',
            'email' => 'patron@boutik.local',
            'role' => 'patron',
        ]);
        $supplier = Fournisseur::create(['nom' => 'Grossiste']);
        $product = Produit::create([
            'nom' => 'Riz',
            'prix_achat' => 1000,
            'prix_vente' => 1500,
            'quantite_stock' => 4,
            'seuil_alerte' => 1,
        ]);
        $purchase = Achat::create([
            'fournisseur_id' => $supplier->id,
            'user_id' => $oldPatron->id,
            'utilisateur_nom_snapshot' => $oldPatron->name,
            'montant_total' => 2000,
            'date_achat' => now()->toDateString(),
        ]);
        $invoice = Facture::create([
            'user_id' => $oldPatron->id,
            'utilisateur_nom_snapshot' => $oldPatron->name,
            'numero' => 'FAC-PATRON123',
            'mode_paiement' => 'especes',
            'montant_total' => 1500,
        ]);
        MouvementCaisse::create([
            'date_jour' => now()->toDateString(),
            'user_id' => $oldPatron->id,
            'utilisateur_nom_snapshot' => $oldPatron->name,
            'type' => 'entree',
            'montant' => 1500,
        ]);
        MouvementStock::create([
            'produit_id' => $product->id,
            'user_id' => $oldPatron->id,
            'utilisateur_nom_snapshot' => $oldPatron->name,
            'type' => 'entree',
            'quantite' => 4,
            'motif' => 'Stock initial',
        ]);

        config()->set('boutik.patron.name', 'Nouveau patron');
        config()->set('boutik.patron.email', 'patron@boutik.local');
        config()->set('boutik.patron.password', 'Patron123');
        config()->set('boutik.patron.recreate_confirmation', 'RECREER_PATRON');

        (new PatronSeeder)->run();

        $newPatron = User::query()->where('email', 'patron@boutik.local')->sole();

        $this->assertNotSame($oldPatron->id, $newPatron->id);
        $this->assertSame('Nouveau patron', $newPatron->name);
        $this->assertSame('patron', $newPatron->role);
        $this->assertTrue(Hash::check('Patron123', $newPatron->password));
        $this->assertDatabaseHas('achats', [
            'id' => $purchase->id,
            'user_id' => null,
            'utilisateur_nom_snapshot' => 'Ancien patron',
        ]);
        $this->assertDatabaseHas('factures', [
            'id' => $invoice->id,
            'user_id' => null,
            'utilisateur_nom_snapshot' => 'Ancien patron',
        ]);
        $this->assertDatabaseHas('mouvements_caisse', [
            'user_id' => null,
            'utilisateur_nom_snapshot' => 'Ancien patron',
        ]);
        $this->assertDatabaseHas('mouvements_stock', [
            'user_id' => null,
            'utilisateur_nom_snapshot' => 'Ancien patron',
        ]);
    }

    public function test_existing_patron_is_not_replaced_without_explicit_confirmation(): void
    {
        $patron = User::factory()->create([
            'email' => 'patron@boutik.local',
            'role' => 'patron',
            'password' => 'ancienne-cle-secrete',
        ]);
        config()->set('boutik.patron.name', 'Nouveau patron');
        config()->set('boutik.patron.email', 'patron@boutik.local');
        config()->set('boutik.patron.password', 'court');
        config()->set('boutik.patron.recreate_confirmation', null);

        (new PatronSeeder)->run();

        $this->assertModelExists($patron);
        $this->assertTrue(Hash::check('ancienne-cle-secrete', $patron->fresh()->password));
        $this->assertDatabaseCount('users', 1);
    }

    public function test_patron_replacement_is_refused_in_production(): void
    {
        User::factory()->create(['role' => 'patron']);
        $this->app['env'] = 'production';
        config()->set('boutik.patron.password', 'mot-de-passe-production-test');
        config()->set('boutik.patron.recreate_confirmation', 'RECREER_PATRON');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('bloqué en production');

        (new PatronSeeder)->run();
    }
}
