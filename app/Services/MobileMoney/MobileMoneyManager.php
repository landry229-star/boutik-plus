<?php

namespace App\Services\MobileMoney;

use App\Models\Facture;
use App\Models\MobileMoneyPayment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class MobileMoneyManager
{
    public function isAvailable(): bool
    {
        return config('mobile_money.enabled') === true
            && config('mobile_money.provider') === 'fedapay'
            && config('mobile_money.currency') === 'XOF'
            && filled(config('mobile_money.providers.fedapay.secret_key'))
            && filled(config('mobile_money.providers.fedapay.webhook_secret'))
            && in_array(config('mobile_money.providers.fedapay.environment'), ['sandbox', 'live'], true);
    }

    public function createCheckout(MobileMoneyPayment $payment, Facture $facture): string
    {
        $amount = (float) $payment->montant;

        if (! is_finite($amount) || $amount <= 0 || $amount !== (float) round($amount)) {
            throw new MobileMoneyGatewayException(
                'Le montant doit être un nombre entier en XOF.',
                safeToReleaseReservation: true,
            );
        }

        $transactionId = $payment->external_reference;
        $providerStatus = 'pending';

        if ($transactionId === null) {
            try {
                $response = $this->client()->post('/transactions', [
                    'description' => 'Paiement facture '.$facture->numero,
                    'amount' => (int) $amount,
                    'currency' => ['iso' => $payment->devise],
                    'callback_url' => route('mobile-money.callback', ['reference' => $payment->reference]),
                    'custom_metadata' => ['reference' => $payment->reference],
                ]);
            } catch (ConnectionException $exception) {
                throw new MobileMoneyGatewayException(
                    'FedaPay n’a pas répondu à la création du paiement. Vérifiez son statut avant toute nouvelle tentative.',
                    previous: $exception,
                );
            }

            if (! $response->successful()) {
                throw new MobileMoneyGatewayException(
                    $response->clientError()
                        ? 'FedaPay a refusé la création du paiement.'
                        : 'La réponse de FedaPay est incertaine. Vérifiez le statut avant toute nouvelle tentative.',
                    safeToReleaseReservation: $response->clientError(),
                );
            }

            $transaction = $this->transactionResource($response->json());
            $transactionId = $this->transactionId($transaction['id'] ?? null);

            if ($transactionId === null) {
                throw new MobileMoneyGatewayException(
                    'FedaPay a créé une transaction avec une réponse inexploitable. Le stock reste réservé.',
                );
            }

            $payment->update([
                'external_reference' => $transactionId,
                'response' => [
                    'transaction_id' => $transactionId,
                    'provider_status' => $transaction['status'] ?? 'pending',
                ],
            ]);
            $providerStatus = (string) ($transaction['status'] ?? 'pending');
        } elseif (! ctype_digit($transactionId)) {
            throw new MobileMoneyGatewayException('La référence de transaction FedaPay est invalide.');
        }

        try {
            $tokenResponse = $this->client()->post('/transactions/'.$transactionId.'/token');
        } catch (ConnectionException $exception) {
            throw new MobileMoneyGatewayException(
                'La transaction FedaPay existe, mais son lien de paiement est temporairement indisponible. Le stock reste réservé.',
                previous: $exception,
            );
        }

        if (! $tokenResponse->successful()) {
            throw new MobileMoneyGatewayException(
                'La transaction FedaPay existe, mais son lien de paiement est temporairement indisponible. Le stock reste réservé.',
            );
        }

        $checkoutUrl = $tokenResponse->json('url');

        if (! is_string($checkoutUrl) || ! $this->isFedaPayUrl($checkoutUrl)) {
            throw new MobileMoneyGatewayException(
                'FedaPay a retourné un lien de paiement invalide. Le stock reste réservé.',
            );
        }

        $payment->update([
            'response' => [
                'transaction_id' => $transactionId,
                'provider_status' => $providerStatus,
                'checkout_url' => $checkoutUrl,
            ],
        ]);

        return $checkoutUrl;
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveTransaction(string $transactionId): array
    {
        if (! ctype_digit($transactionId)) {
            throw new MobileMoneyGatewayException('La référence de transaction FedaPay est invalide.');
        }

        try {
            $response = $this->client()->get('/transactions/'.$transactionId);
        } catch (ConnectionException $exception) {
            throw new MobileMoneyGatewayException(
                'Le statut du paiement ne peut pas être vérifié auprès de FedaPay.',
                previous: $exception,
            );
        }

        if (! $response->successful()) {
            throw new MobileMoneyGatewayException(
                'Le statut du paiement ne peut pas être vérifié auprès de FedaPay.',
            );
        }

        return $this->transactionResource($response->json());
    }

    public function verifyWebhookSignature(string $payload, string $signatureHeader): bool
    {
        $secret = (string) config('mobile_money.providers.fedapay.webhook_secret');

        if ($secret === '' || $signatureHeader === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signatureHeader) as $item) {
            $parts = explode('=', trim($item), 2);

            if (count($parts) !== 2) {
                continue;
            }

            if ($parts[0] === 't' && ctype_digit($parts[1])) {
                $timestamp ??= (int) $parts[1];
            } elseif ($parts[0] === 's') {
                $signatures[] = $parts[1];
            }
        }

        if ($timestamp === null || $signatures === [] || abs(now()->timestamp - $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    private function client(): PendingRequest
    {
        $environment = config('mobile_money.providers.fedapay.environment');
        $baseUrl = $environment === 'live'
            ? 'https://api.fedapay.com/v1'
            : 'https://sandbox-api.fedapay.com/v1';
        $timeout = max(2, min((int) config('mobile_money.timeout', 15), 60));

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->withToken((string) config('mobile_money.providers.fedapay.secret_key'))
            ->connectTimeout(min($timeout, 5))
            ->timeout($timeout);
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionResource(mixed $response): array
    {
        if (! is_array($response)) {
            throw new MobileMoneyGatewayException('La réponse de FedaPay est invalide.');
        }

        foreach (['transaction', 'v1/transaction'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }

        if (isset($response['data']) && is_array($response['data'])) {
            foreach (['transaction', 'v1/transaction'] as $key) {
                if (isset($response['data'][$key]) && is_array($response['data'][$key])) {
                    return $response['data'][$key];
                }
            }

            if (isset($response['data']['id'])) {
                return $response['data'];
            }
        }

        return $response;
    }

    private function transactionId(mixed $id): ?string
    {
        if (is_int($id) && $id > 0) {
            return (string) $id;
        }

        if (is_string($id) && ctype_digit($id) && (int) $id > 0) {
            return $id;
        }

        return null;
    }

    private function isFedaPayUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts)) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        return ($parts['scheme'] ?? null) === 'https'
            && ($host === 'fedapay.com' || str_ends_with($host, '.fedapay.com'));
    }
}
