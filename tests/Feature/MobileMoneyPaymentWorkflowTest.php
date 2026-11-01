<?php

namespace Tests\Feature;

use App\Models\Facture;
use App\Models\MobileMoneyPayment;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobileMoneyPaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'mobile_money.enabled' => true,
            'mobile_money.provider' => 'fedapay',
            'mobile_money.currency' => 'XOF',
            'mobile_money.timeout' => 10,
            'mobile_money.providers.fedapay.environment' => 'sandbox',
            'mobile_money.providers.fedapay.secret_key' => 'test-secret-key',
            'mobile_money.providers.fedapay.webhook_secret' => 'test-webhook-secret',
            'boutik.tax.enabled' => false,
        ]);
    }

    public function test_fedapay_checkout_reserves_stock_without_marking_invoice_paid_or_recording_cash(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://sandbox-api.fedapay.com/v1/transactions' => Http::response([
                'id' => 600,
                'status' => 'pending',
            ], 201),
            'https://sandbox-api.fedapay.com/v1/transactions/600/token' => Http::response([
                'token' => 'checkout-token',
                'url' => 'https://checkout.fedapay.com/pay/checkout-token',
            ]),
        ]);
        $employee = User::factory()->create(['role' => 'employe']);
        $product = $this->createProduct(5);

        $response = $this->actingAs($employee)->post(route('factures.store'), [
            'mode_paiement' => 'fedapay',
            'produits' => [['id' => $product->id, 'quantite' => 2]],
        ]);
        $invoice = Facture::query()->sole();
        $payment = MobileMoneyPayment::query()->sole();

        $response->assertRedirect('https://checkout.fedapay.com/pay/checkout-token');
        $this->assertDatabaseHas('factures', [
            'id' => $invoice->id,
            'mode_paiement' => 'mobile_money',
            'statut' => 'en_attente',
            'montant_total' => 3000,
        ]);
        $this->assertDatabaseHas('mobile_money_payments', [
            'id' => $payment->id,
            'provider' => 'fedapay',
            'status' => 'pending',
            'external_reference' => '600',
            'montant' => 3000,
            'devise' => 'XOF',
        ]);
        $this->assertDatabaseHas('produits', ['id' => $product->id, 'quantite_stock' => 3]);
        $this->assertDatabaseMissing('mouvements_caisse', ['facture_id' => $invoice->id]);
        $this->assertDatabaseHas('mouvements_stock', [
            'facture_id' => $invoice->id,
            'type' => 'sortie',
            'quantite' => 2,
        ]);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sandbox-api.fedapay.com/v1/transactions'
            && $request->method() === 'POST'
            && $request['amount'] === 3000
            && $request['currency']['iso'] === 'XOF'
            && $request['callback_url'] === route('mobile-money.callback', ['reference' => $payment->reference])
            && $request['custom_metadata']['reference'] === $payment->reference);
    }

    public function test_approved_webhook_verifies_fedapay_transaction_and_records_cash_only_once(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://sandbox-api.fedapay.com/v1/transactions' => Http::response([
                'id' => 600,
                'status' => 'pending',
            ], 201),
            'https://sandbox-api.fedapay.com/v1/transactions/600/token' => Http::response([
                'url' => 'https://checkout.fedapay.com/pay/checkout-token',
            ]),
            'https://sandbox-api.fedapay.com/v1/transactions/600' => Http::response(
                $this->providerTransaction('approved')
            ),
        ]);
        $employee = User::factory()->create(['role' => 'employe']);
        $product = $this->createProduct(5);
        $this->startOnlineSale($employee, $product);
        $payment = MobileMoneyPayment::query()->sole();
        $payload = json_encode([
            'name' => 'transaction.approved',
            'object_id' => 600,
        ], JSON_THROW_ON_ERROR);
        $signature = $this->signatureFor($payload);

        $this->postWebhook($payload, $signature)->assertOk();
        $this->postWebhook($payload, $signature)->assertOk();

        $this->assertDatabaseHas('factures', ['id' => $payment->facture_id, 'statut' => 'payee']);
        $this->assertDatabaseHas('mobile_money_payments', [
            'id' => $payment->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseCount('mouvements_caisse', 1);
        $this->assertDatabaseHas('mouvements_caisse', [
            'facture_id' => $payment->facture_id,
            'type' => 'entree',
            'moyen_paiement' => 'mobile_money',
            'montant' => 3000,
        ]);
        $this->assertDatabaseHas('produits', ['id' => $product->id, 'quantite_stock' => 3]);
    }

    public function test_fedapay_return_redirect_confirms_payment_only_after_server_side_status_check(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://sandbox-api.fedapay.com/v1/transactions' => Http::response([
                'id' => 600,
                'status' => 'pending',
            ], 201),
            'https://sandbox-api.fedapay.com/v1/transactions/600/token' => Http::response([
                'url' => 'https://checkout.fedapay.com/pay/checkout-token',
            ]),
            'https://sandbox-api.fedapay.com/v1/transactions/600' => Http::response(
                $this->providerTransaction('approved')
            ),
        ]);
        $employee = User::factory()->create(['role' => 'employe']);
        $product = $this->createProduct(5);
        $invoice = $this->startOnlineSale($employee, $product);
        $payment = MobileMoneyPayment::query()->sole();

        $this->get(route('mobile-money.callback', ['reference' => $payment->reference]))
            ->assertRedirect(route('factures.show', $invoice));

        $this->assertDatabaseHas('factures', ['id' => $invoice->id, 'statut' => 'payee']);
        $this->assertDatabaseHas('mobile_money_payments', ['id' => $payment->id, 'status' => 'approved']);
        $this->assertDatabaseCount('mouvements_caisse', 1);
    }

    public function test_invalid_fedapay_signature_cannot_change_invoice_or_stock(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://sandbox-api.fedapay.com/v1/transactions' => Http::response([
                'id' => 600,
                'status' => 'pending',
            ], 201),
            'https://sandbox-api.fedapay.com/v1/transactions/600/token' => Http::response([
                'url' => 'https://checkout.fedapay.com/pay/checkout-token',
            ]),
        ]);
        $employee = User::factory()->create(['role' => 'employe']);
        $product = $this->createProduct(5);
        $this->startOnlineSale($employee, $product);
        $payment = MobileMoneyPayment::query()->sole();
        $payload = json_encode(['name' => 'transaction.approved', 'object_id' => 600], JSON_THROW_ON_ERROR);

        $this->postWebhook($payload, 't='.now()->timestamp.',s=invalid')
            ->assertBadRequest();

        $this->assertDatabaseHas('factures', ['id' => $payment->facture_id, 'statut' => 'en_attente']);
        $this->assertDatabaseHas('mobile_money_payments', ['id' => $payment->id, 'status' => 'pending']);
        $this->assertDatabaseHas('produits', ['id' => $product->id, 'quantite_stock' => 3]);
        $this->assertDatabaseCount('mouvements_caisse', 0);
    }

    public function test_canceled_webhook_releases_reserved_stock_without_recording_cash(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://sandbox-api.fedapay.com/v1/transactions' => Http::response([
                'id' => 600,
                'status' => 'pending',
            ], 201),
            'https://sandbox-api.fedapay.com/v1/transactions/600/token' => Http::response([
                'url' => 'https://checkout.fedapay.com/pay/checkout-token',
            ]),
            'https://sandbox-api.fedapay.com/v1/transactions/600' => Http::response(
                $this->providerTransaction('canceled')
            ),
        ]);
        $employee = User::factory()->create(['role' => 'employe']);
        $product = $this->createProduct(5);
        $this->startOnlineSale($employee, $product);
        $payment = MobileMoneyPayment::query()->sole();
        $payload = json_encode([
            'type' => 'transaction.canceled',
            'object_id' => 600,
        ], JSON_THROW_ON_ERROR);

        $this->postWebhook($payload, $this->signatureFor($payload))->assertOk();

        $this->assertDatabaseHas('factures', ['id' => $payment->facture_id, 'statut' => 'annulee']);
        $this->assertDatabaseHas('mobile_money_payments', ['id' => $payment->id, 'status' => 'canceled']);
        $this->assertDatabaseHas('produits', ['id' => $product->id, 'quantite_stock' => 5]);
        $this->assertDatabaseCount('mouvements_caisse', 0);
        $this->assertDatabaseCount('mouvements_stock', 2);
        $this->assertDatabaseHas('mouvements_stock', [
            'facture_id' => $payment->facture_id,
            'type' => 'entree',
            'quantite' => 2,
        ]);
    }

    public function test_fedapay_amount_mismatch_is_rejected_without_confirming_invoice(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://sandbox-api.fedapay.com/v1/transactions' => Http::response([
                'id' => 600,
                'status' => 'pending',
            ], 201),
            'https://sandbox-api.fedapay.com/v1/transactions/600/token' => Http::response([
                'url' => 'https://checkout.fedapay.com/pay/checkout-token',
            ]),
            'https://sandbox-api.fedapay.com/v1/transactions/600' => Http::response(
                $this->providerTransaction('approved', amount: 3001)
            ),
        ]);
        $employee = User::factory()->create(['role' => 'employe']);
        $product = $this->createProduct(5);
        $this->startOnlineSale($employee, $product);
        $payment = MobileMoneyPayment::query()->sole();
        $payload = json_encode([
            'name' => 'transaction.approved',
            'object_id' => 600,
        ], JSON_THROW_ON_ERROR);

        $this->postWebhook($payload, $this->signatureFor($payload))->assertStatus(422);

        $this->assertDatabaseHas('factures', ['id' => $payment->facture_id, 'statut' => 'en_attente']);
        $this->assertDatabaseHas('mobile_money_payments', ['id' => $payment->id, 'status' => 'pending']);
        $this->assertDatabaseCount('mouvements_caisse', 0);
    }

    public function test_fedapay_decline_response_releases_stock_and_cancels_local_invoice(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://sandbox-api.fedapay.com/v1/transactions' => Http::response([
                'id' => 600,
                'status' => 'pending',
            ], 201),
            'https://sandbox-api.fedapay.com/v1/transactions/600/token' => Http::response([
                'url' => 'https://checkout.fedapay.com/pay/checkout-token',
            ]),
            'https://sandbox-api.fedapay.com/v1/transactions/600' => Http::response(
                $this->providerTransaction('declined')
            ),
        ]);
        $employee = User::factory()->create(['role' => 'employe']);
        $product = $this->createProduct(5);
        $this->startOnlineSale($employee, $product);
        $payment = MobileMoneyPayment::query()->sole();

        $this->actingAs($employee)
            ->post(route('factures.paiement.verifier', $payment->facture_id))
            ->assertRedirect(route('factures.show', $payment->facture_id))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('factures', ['id' => $payment->facture_id, 'statut' => 'annulee']);
        $this->assertDatabaseHas('mobile_money_payments', ['id' => $payment->id, 'status' => 'declined']);
        $this->assertDatabaseHas('produits', ['id' => $product->id, 'quantite_stock' => 5]);
        $this->assertDatabaseCount('mouvements_caisse', 0);
        $this->assertDatabaseCount('mouvements_stock', 2);
    }

    public function test_checkout_link_can_be_retried_without_creating_a_second_fedapay_transaction(): void
    {
        Http::preventStrayRequests();
        $transactionCreations = 0;
        $tokenResponses = Http::sequence()
            ->push(['message' => 'temporary failure'], 500)
            ->push(['url' => 'https://checkout.fedapay.com/pay/retried-token']);
        Http::fake([
            'https://sandbox-api.fedapay.com/v1/transactions' => function (Request $request) use (&$transactionCreations) {
                $transactionCreations++;

                return Http::response(['id' => 600, 'status' => 'pending'], 201);
            },
            'https://sandbox-api.fedapay.com/v1/transactions/600/token' => $tokenResponses,
            'https://sandbox-api.fedapay.com/v1/transactions/600' => Http::response(
                $this->providerTransaction('pending')
            ),
        ]);
        $employee = User::factory()->create(['role' => 'employe']);
        $product = $this->createProduct(5);

        $response = $this->actingAs($employee)->post(route('factures.store'), [
            'mode_paiement' => 'fedapay',
            'produits' => [['id' => $product->id, 'quantite' => 2]],
        ]);
        $invoice = Facture::query()->sole();
        $payment = MobileMoneyPayment::query()->sole();

        $response->assertRedirect(route('factures.show', $invoice))
            ->assertSessionHas('warning');

        $this->actingAs($employee)
            ->post(route('factures.paiement.reprendre', $invoice))
            ->assertRedirect('https://checkout.fedapay.com/pay/retried-token');

        $this->assertSame(1, $transactionCreations);
        $this->assertDatabaseHas('mobile_money_payments', [
            'id' => $payment->id,
            'external_reference' => '600',
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('produits', ['id' => $product->id, 'quantite_stock' => 3]);
    }

    public function test_fedapay_is_rejected_when_the_provider_is_not_configured(): void
    {
        config()->set('mobile_money.enabled', false);
        Http::preventStrayRequests();
        Http::fake();
        $employee = User::factory()->create(['role' => 'employe']);
        $product = $this->createProduct(5);

        $this->actingAs($employee)
            ->post(route('factures.store'), [
                'mode_paiement' => 'fedapay',
                'produits' => [['id' => $product->id, 'quantite' => 2]],
            ])
            ->assertSessionHasErrors('mode_paiement');

        $this->assertDatabaseCount('factures', 0);
        $this->assertDatabaseHas('produits', ['id' => $product->id, 'quantite_stock' => 5]);
        $this->assertDatabaseCount('mouvements_caisse', 0);
    }

    public function test_checkout_creation_rejection_releases_stock_and_does_not_record_cash(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://sandbox-api.fedapay.com/v1/transactions' => Http::response([
                'message' => 'Invalid merchant configuration',
            ], 422),
        ]);
        $employee = User::factory()->create(['role' => 'employe']);
        $product = $this->createProduct(5);

        $response = $this->actingAs($employee)->post(route('factures.store'), [
            'mode_paiement' => 'fedapay',
            'produits' => [['id' => $product->id, 'quantite' => 2]],
        ]);
        $invoice = Facture::query()->sole();
        $payment = MobileMoneyPayment::query()->sole();

        $response->assertRedirect(route('factures.show', $invoice))
            ->assertSessionHasErrors('paiement');
        $this->assertDatabaseHas('factures', ['id' => $invoice->id, 'statut' => 'annulee']);
        $this->assertDatabaseHas('mobile_money_payments', ['id' => $payment->id, 'status' => 'failed']);
        $this->assertDatabaseHas('produits', ['id' => $product->id, 'quantite_stock' => 5]);
        $this->assertDatabaseCount('mouvements_caisse', 0);
        $this->assertDatabaseCount('mouvements_stock', 2);
    }

    public function test_fedapay_rejects_a_correctly_signed_but_expired_webhook(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $payload = json_encode(['name' => 'transaction.approved', 'object_id' => 600], JSON_THROW_ON_ERROR);
        $signature = $this->signatureFor($payload, now()->subSeconds(301)->timestamp);

        $this->postWebhook($payload, $signature)->assertBadRequest();
    }

    public function test_fedapay_paid_invoice_cannot_be_cancelled_without_a_provider_refund(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $invoice = Facture::create([
            'numero' => 'FAC-FEDAPAY1',
            'mode_paiement' => 'mobile_money',
            'montant_total' => 3000,
            'statut' => 'payee',
        ]);
        $invoice->paiementsMobileMoney()->create([
            'provider' => 'fedapay',
            'status' => 'approved',
            'reference' => 'MM-FEDAPAY1',
            'external_reference' => '600',
            'montant' => 3000,
            'devise' => 'XOF',
            'operation' => 'collect',
            'confirmed_at' => now(),
        ]);

        $this->actingAs($patron)
            ->post(route('factures.annuler', $invoice), ['motif_annulation' => 'Erreur de vente'])
            ->assertSessionHasErrors([
                'facture' => 'Les remboursements FedaPay ne sont pas intégrés. Cette facture ne peut pas être annulée dans Boutik Plus.',
            ]);

        $this->assertDatabaseHas('factures', ['id' => $invoice->id, 'statut' => 'payee']);
        $this->assertDatabaseCount('mouvements_caisse', 0);
        $this->actingAs($patron)
            ->get(route('factures.show', $invoice))
            ->assertOk()
            ->assertDontSee(route('factures.annuler', $invoice));
    }

    private function startOnlineSale(User $employee, Produit $product): Facture
    {
        $this->actingAs($employee)->post(route('factures.store'), [
            'mode_paiement' => 'fedapay',
            'produits' => [['id' => $product->id, 'quantite' => 2]],
        ])->assertRedirect('https://checkout.fedapay.com/pay/checkout-token');

        return Facture::query()->sole();
    }

    /**
     * @return array<string, mixed>
     */
    private function providerTransaction(string $status, int $amount = 3000): array
    {
        return [
            'id' => 600,
            'amount' => $amount,
            'currency' => ['iso' => 'XOF'],
            'status' => $status,
        ];
    }

    private function signatureFor(string $payload, ?int $timestamp = null): string
    {
        $timestamp ??= now()->timestamp;

        return 't='.$timestamp.',s='.hash_hmac('sha256', $timestamp.'.'.$payload, 'test-webhook-secret');
    }

    private function postWebhook(string $payload, string $signature)
    {
        return $this->call('POST', route('webhooks.fedapay', absolute: false), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FEDAPAY_SIGNATURE' => $signature,
        ], $payload);
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
