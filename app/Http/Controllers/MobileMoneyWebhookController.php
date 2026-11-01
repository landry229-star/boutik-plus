<?php

namespace App\Http\Controllers;

use App\Services\MobileMoney\MobileMoneyGatewayException;
use App\Services\MobileMoney\MobileMoneyIntegrityException;
use App\Services\MobileMoney\MobileMoneyManager;
use App\Services\MobileMoney\MobileMoneyPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JsonException;

class MobileMoneyWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        MobileMoneyManager $mobileMoneyManager,
        MobileMoneyPaymentService $mobileMoneyPaymentService,
    ): JsonResponse {
        $payload = $request->getContent();
        $signature = (string) $request->header('X-FEDAPAY-SIGNATURE', '');

        if (! $mobileMoneyManager->verifyWebhookSignature($payload, $signature)) {
            Log::warning('Webhook FedaPay rejeté : signature invalide.', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Signature invalide.'], 400);
        }

        try {
            $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json(['message' => 'Contenu JSON invalide.'], 400);
        }

        if (! is_array($event)) {
            return response()->json(['message' => 'Événement invalide.'], 400);
        }

        $eventName = $event['name'] ?? $event['type'] ?? null;
        $handledEvents = [
            'transaction.approved',
            'transaction.canceled',
            'transaction.declined',
        ];

        if (! is_string($eventName) || ! in_array($eventName, $handledEvents, true)) {
            return response()->json(['received' => true]);
        }

        $entity = is_array($event['entity'] ?? null) ? $event['entity'] : [];
        $data = is_array($event['data'] ?? null) ? $event['data'] : [];
        $transactionId = $event['object_id']
            ?? $data['object_id']
            ?? $entity['id']
            ?? null;

        if ((! is_int($transactionId) && ! is_string($transactionId))
            || ! ctype_digit((string) $transactionId)) {
            return response()->json(['message' => 'Référence de transaction invalide.'], 400);
        }

        try {
            $transaction = $mobileMoneyManager->retrieveTransaction((string) $transactionId);
        } catch (MobileMoneyGatewayException $exception) {
            Log::warning('La transaction du webhook FedaPay ne peut pas être vérifiée.', [
                'transaction_id' => (string) $transactionId,
            ]);

            return response()->json(['message' => 'Transaction temporairement indisponible.'], 503);
        }

        $payment = $mobileMoneyPaymentService->findForTransaction($transaction);

        if (! $payment) {
            Log::info('Événement FedaPay ignoré : aucune référence locale correspondante.', [
                'transaction_id' => (string) $transactionId,
            ]);

            return response()->json(['received' => true]);
        }

        try {
            $mobileMoneyPaymentService->synchronize($payment, $transaction);
        } catch (MobileMoneyIntegrityException $exception) {
            Log::warning('Webhook FedaPay rejeté : transaction incohérente.', [
                'reference' => $payment->reference,
                'transaction_id' => (string) $transactionId,
            ]);

            return response()->json(['message' => 'La transaction ne correspond pas au paiement attendu.'], 422);
        }

        return response()->json(['received' => true]);
    }
}
