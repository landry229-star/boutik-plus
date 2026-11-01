<?php

namespace Tests\Feature;

use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProduitPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_patron_can_download_a_branded_product_sheet_with_catalogue_details(): void
    {
        config()->set('boutik.company', [
            'name' => 'Boutique Exemple',
            'address' => '12 rue du Marché, Cotonou',
            'phone' => '+229 97 00 00 00',
            'email' => 'contact@boutique.example',
            'ifu' => '3200000000000',
            'rccm' => 'RB/COT/20 B 12345',
            'logo_path' => null,
            'legal_notice' => 'Document de gestion interne.',
        ]);
        config()->set('boutik.currency', 'FCFA');

        $patron = User::factory()->create(['role' => 'patron']);
        $product = Produit::create([
            'nom' => 'Riz local',
            'categorie' => 'Épicerie',
            'code_barre' => '1234567890123',
            'prix_achat' => 1000,
            'prix_vente' => 1500,
            'quantite_stock' => 8,
            'seuil_alerte' => 2,
        ]);

        $this->actingAs($patron)
            ->get(route('produits.pdf', $product))
            ->assertDownload('fiche-produit-riz-local.pdf')
            ->assertHeader('content-type', 'application/pdf');

        $html = view('produits.pdf', [
            'produit' => $product,
            'generatedAt' => now(),
            'company' => config('boutik.company'),
            'currency' => config('boutik.currency'),
            'logoDataUri' => null,
        ])->render();

        $this->assertStringContainsString('Boutique Exemple', $html);
        $this->assertStringContainsString('12 rue du Marché, Cotonou', $html);
        $this->assertStringContainsString('IFU : 3200000000000', $html);
        $this->assertStringContainsString('RCCM : RB/COT/20 B 12345', $html);
        $this->assertStringContainsString('FICHE PRODUIT', $html);
        $this->assertStringContainsString('1234567890123', $html);
        $this->assertStringContainsString("Prix d'achat", $html);
        $this->assertStringContainsString('Stock disponible', $html);
        $this->assertStringContainsString('Document de gestion interne.', $html);
    }

    public function test_product_pdf_is_restricted_to_patron_and_requires_authentication(): void
    {
        $product = Produit::create([
            'nom' => 'Riz local',
            'prix_achat' => 1000,
            'prix_vente' => 1500,
            'quantite_stock' => 8,
            'seuil_alerte' => 2,
        ]);

        $this->get(route('produits.pdf', $product))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create(['role' => 'employe']))
            ->get(route('produits.pdf', $product))
            ->assertForbidden();
    }
}
