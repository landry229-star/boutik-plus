<?php

namespace Tests\Feature;

use App\Models\Achat;
use App\Models\Fournisseur;
use App\Models\MouvementCaisse;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchatPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_patron_can_download_a_branded_purchase_pdf_with_supplier_and_payment_details(): void
    {
        config()->set('boutik.company', [
            'name' => 'Boutique Exemple',
            'address' => '12 rue du Marché, Cotonou',
            'phone' => '+229 97 00 00 00',
            'email' => 'contact@boutique.example',
            'ifu' => '3200000000000',
            'rccm' => 'RB/COT/20 B 12345',
            'logo_path' => null,
            'legal_notice' => 'Document de suivi interne.',
        ]);
        config()->set('boutik.currency', 'FCFA');

        $patron = User::factory()->create(['role' => 'patron']);
        $supplier = Fournisseur::create([
            'nom' => 'Grossiste Exemple',
            'telephone' => '+229 96 00 00 00',
            'adresse' => 'Akpakpa, Cotonou',
        ]);
        $product = Produit::create([
            'nom' => 'Riz local',
            'categorie' => 'Épicerie',
            'prix_achat' => 1000,
            'prix_vente' => 1500,
            'quantite_stock' => 8,
            'seuil_alerte' => 2,
        ]);
        $purchase = Achat::create([
            'fournisseur_id' => $supplier->id,
            'user_id' => $patron->id,
            'utilisateur_nom_snapshot' => $patron->name,
            'montant_total' => 2500,
            'date_achat' => now()->toDateString(),
            'notes' => 'Livraison du matin.',
        ]);
        $purchase->lignes()->create([
            'produit_id' => $product->id,
            'quantite' => 2,
            'prix_achat_unitaire' => 1250,
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
            ->get(route('achats.pdf', $purchase))
            ->assertDownload('achat-'.$purchase->id.'.pdf')
            ->assertHeader('content-type', 'application/pdf');

        $purchase->load(['fournisseur', 'utilisateur', 'lignes.produit', 'annuleePar']);
        $payments = $purchase->mouvementsCaisse()->where('type', 'sortie')->get();
        $html = view('achats.pdf', [
            'achat' => $purchase,
            'paiements' => $payments,
            'montantPaye' => 500.0,
            'montantDu' => 2000.0,
            'company' => config('boutik.company'),
            'currency' => config('boutik.currency'),
            'logoDataUri' => null,
        ])->render();

        $this->assertStringContainsString('Boutique Exemple', $html);
        $this->assertStringContainsString('IFU : 3200000000000', $html);
        $this->assertStringContainsString('RCCM : RB/COT/20 B 12345', $html);
        $this->assertStringContainsString('Grossiste Exemple', $html);
        $this->assertStringContainsString('Akpakpa, Cotonou', $html);
        $this->assertStringContainsString('Riz local', $html);
        $this->assertStringContainsString('Paiements enregistrés', $html);
        $this->assertStringContainsString('Reste dû : 2 000 FCFA', $html);
        $this->assertStringContainsString('Livraison du matin.', $html);
    }

    public function test_purchase_pdf_is_restricted_to_patron_and_requires_authentication(): void
    {
        $supplier = Fournisseur::create(['nom' => 'Grossiste']);
        $purchase = Achat::create([
            'fournisseur_id' => $supplier->id,
            'montant_total' => 1000,
            'date_achat' => now()->toDateString(),
        ]);

        $this->get(route('achats.pdf', $purchase))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'employe']))
            ->get(route('achats.pdf', $purchase))
            ->assertForbidden();
    }
}
