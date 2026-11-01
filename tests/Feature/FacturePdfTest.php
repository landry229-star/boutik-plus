<?php

namespace Tests\Feature;

use App\Models\Facture;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacturePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_download_simple_and_detailed_invoice_pdfs(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);
        $product = Produit::create([
            'nom' => 'Riz local',
            'prix_achat' => 1000,
            'prix_vente' => 1500,
            'quantite_stock' => 3,
            'seuil_alerte' => 1,
        ]);
        $invoice = Facture::create([
            'user_id' => $employee->id,
            'numero' => 'FAC-TEST-PDF',
            'client_nom' => 'Client de test',
            'montant_total' => 1500,
            'montant_ht' => 1500,
            'montant_taxe' => 0,
            'taux_taxe' => 0,
            'mode_paiement' => 'especes',
        ]);
        $invoice->lignes()->create([
            'produit_id' => $product->id,
            'quantite' => 1,
            'prix_unitaire' => 1500,
            'sous_total' => 1500,
        ]);

        $simpleResponse = $this->actingAs($employee)->get(route('factures.pdf', [
            'facture' => $invoice,
            'format' => 'simple',
        ]));
        $detailedResponse = $this->actingAs($employee)->get(route('factures.pdf', [
            'facture' => $invoice,
            'format' => 'complet',
        ]));

        $simpleResponse->assertDownload('fac-test-pdf.pdf');
        $simpleResponse->assertHeader('content-type', 'application/pdf');
        $this->assertSame('%PDF', substr($simpleResponse->getContent(), 0, 4));
        $detailedResponse->assertDownload('fac-test-pdf.pdf');
        $detailedResponse->assertHeader('content-type', 'application/pdf');
        $this->assertSame('%PDF', substr($detailedResponse->getContent(), 0, 4));
        $this->assertNotSame($simpleResponse->getContent(), $detailedResponse->getContent());
    }

    public function test_invoice_pdf_displays_configured_shop_identity_and_sales_details(): void
    {
        config()->set('boutik.company', [
            'name' => 'Boutique Exemple',
            'address' => '12 rue du Marché, Cotonou',
            'phone' => '+229 97 00 00 00',
            'email' => 'contact@boutique.example',
            'ifu' => '3200000000000',
            'rccm' => 'RB/COT/20 B 12345',
            'logo_path' => null,
            'legal_notice' => 'Conservez ce reçu.',
        ]);
        config()->set('boutik.currency', 'FCFA');

        $employee = User::factory()->create(['role' => 'employe']);
        $product = Produit::create([
            'nom' => 'Riz local',
            'prix_achat' => 1000,
            'prix_vente' => 1500,
            'quantite_stock' => 3,
            'seuil_alerte' => 1,
        ]);
        $invoice = Facture::create([
            'user_id' => $employee->id,
            'numero' => 'FAC-INFO-PDF',
            'client_nom' => 'Client Exemple',
            'client_telephone' => '+229 96 00 00 00',
            'client_email' => 'client@example.com',
            'montant_total' => 1650,
            'montant_ht' => 1500,
            'montant_taxe' => 150,
            'taux_taxe' => 10,
            'libelle_taxe' => 'TVA',
            'mode_paiement' => 'especes',
        ]);
        $invoice->lignes()->create([
            'produit_id' => $product->id,
            'quantite' => 1,
            'prix_unitaire' => 1500,
            'sous_total' => 1500,
        ]);

        $invoice->load('lignes.produit', 'utilisateur');

        foreach (['simple', 'complet'] as $format) {
            $html = view('factures.pdf', [
                'facture' => $invoice,
                'format' => $format,
                'company' => config('boutik.company'),
                'currency' => config('boutik.currency'),
                'logoDataUri' => null,
            ])->render();

            $this->assertStringContainsString('Boutique Exemple', $html);
            $this->assertStringContainsString('12 rue du Marché, Cotonou', $html);
            $this->assertStringContainsString('+229 97 00 00 00', $html);
            $this->assertStringContainsString('contact@boutique.example', $html);
            $this->assertStringContainsString('IFU : 3200000000000', $html);
            $this->assertStringContainsString('RCCM : RB/COT/20 B 12345', $html);
            $this->assertStringContainsString('FAC-INFO-PDF', $html);
            $this->assertStringContainsString('Client Exemple', $html);
            $this->assertStringContainsString('Riz local', $html);
            $this->assertStringContainsString('TVA (10,00 %)', $html);
            $this->assertStringContainsString('Total à payer', $html);
            $this->assertStringContainsString('Merci pour votre confiance et votre achat.', $html);
        }
    }

    public function test_invoice_detail_displays_the_configured_shop_identity(): void
    {
        config()->set('boutik.company.name', 'Boutique Exemple');
        config()->set('boutik.company.address', '12 rue du Marché, Cotonou');
        config()->set('boutik.company.phone', '+229 97 00 00 00');
        config()->set('boutik.company.email', 'contact@boutique.example');
        config()->set('boutik.company.ifu', '3200000000000');
        config()->set('boutik.company.rccm', 'RB/COT/20 B 12345');

        $employee = User::factory()->create(['role' => 'employe']);
        $invoice = Facture::create([
            'user_id' => $employee->id,
            'numero' => 'FAC-INFO-WEB',
            'montant_total' => 0,
            'mode_paiement' => 'especes',
        ]);

        $this->actingAs($employee)
            ->get(route('factures.show', $invoice))
            ->assertOk()
            ->assertSee('Boutique Exemple')
            ->assertSee('12 rue du Marché, Cotonou')
            ->assertSee('+229 97 00 00 00')
            ->assertSee('contact@boutique.example')
            ->assertSee('IFU : 3200000000000')
            ->assertSee('RCCM : RB/COT/20 B 12345');
    }

    public function test_invoice_pdf_requires_authentication_and_rejects_unknown_formats(): void
    {
        $invoice = Facture::create([
            'numero' => 'FAC-TEST-AUTH',
            'montant_total' => 0,
            'mode_paiement' => 'especes',
        ]);

        $this->get(route('factures.pdf', $invoice))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('factures.pdf', ['facture' => $invoice, 'format' => 'unknown']))
            ->assertSessionHasErrors('format');
    }
}
