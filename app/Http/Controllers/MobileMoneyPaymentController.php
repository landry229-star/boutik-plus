<?php

namespace App\Http\Controllers;

use App\Models\Facture;
use App\Models\MobileMoneyPayment;
use App\Services\MobileMoney\MobileMoneyGatewayException;
use App\Services\MobileMoney\MobileMoneyIntegrityException;
use App\Services\MobileMoney\MobileMoneyManager;
use App\Services\MobileMoney\MobileMoneyPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MobileMoneyPaymentController extends Controller
{
    public function callback(
        Request $request,
        string $reference,
        MobileMoneyManager $mobileMoneyManager,
        MobileMoneyPaymentService $mobileMoneyPaymentService,
    ): JsonResponse|RedirectResponse {
        $payment = MobileMoneyPayment::query()
            ->with('facture')
            ->where('provider', 'fedapay')
            ->where('reference', $reference)
            ->firstOrFail();
        $status = $payment->status;

        if ($payment->external_reference) {
            try {
                $transaction = $mobileMoneyManager->retrieveTransaction($payment->external_reference);
                $status = $mobileMoneyPaymentService->synchronize($payment, $transaction);
            } catch (MobileMoneyGatewayException $exception) {
                Log::warning('Le retour FedaPay n’a pas permis de vérifier la transaction.', [
                    'reference' => $payment->reference,
                ]);

                if ($request->isMethod('post')) {
                    return response()->json(['message' => 'Vérification temporairement indisponible.'], 503);
                }

                return redirect()->route('factures.show', $payment->facture)
                    ->with('warning', 'Le paiement n’a pas encore pu être vérifié auprès de FedaPay.');
            } catch (MobileMoneyIntegrityException $exception) {
                Log::warning('Le retour FedaPay a détecté une transaction incohérente.', [
                    'reference' => $payment->reference,
                    'transaction_id' => $payment->external_reference,
                ]);

                if ($request->isMethod('post')) {
                    return response()->json(['message' => 'La transaction ne correspond pas au paiement attendu.'], 422);
                }

                return redirect()->route('factures.show', $payment->facture)
                    ->withErrors(['paiement' => 'Les informations reçues ne correspondent pas à cette facture. Contactez le responsable de la boutique.']);
            }
        }

        if ($request->isMethod('post')) {
            return response()->json(['received' => true]);
        }

        $message = match ($status) {
            'approved' => ['success', 'Paiement FedaPay confirmé.'],
            'canceled', 'declined', 'failed' => ['warning', 'Le paiement a échoué ou a été annulé. Le stock a été libéré.'],
            default => ['warning', 'FedaPay indique que le paiement est toujours en attente.'],
        };

        return redirect()->route('factures.show', $payment->facture)->with($message[0], $message[1]);
    }

    public function resume(
        Facture $facture,
        MobileMoneyManager $mobileMoneyManager,
        MobileMoneyPaymentService $mobileMoneyPaymentService,
    ): RedirectResponse {
        if ($facture->statut !== 'en_attente') {
            return back()->withErrors(['paiement' => 'Cette facture ne possède aucun paiement en attente.']);
        }

        $payment = $facture->paiementsMobileMoney()
            ->where('provider', 'fedapay')
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if (! $payment || ! $payment->external_reference) {
            return back()->withErrors(['paiement' => 'Le paiement FedaPay n’est pas encore disponible pour une nouvelle vérification.']);
        }

        try {
            $transaction = $mobileMoneyManager->retrieveTransaction($payment->external_reference);
            $status = $mobileMoneyPaymentService->synchronize($payment, $transaction);

            if ($status !== 'pending') {
                return redirect()->route('factures.show', $facture)
                    ->with('warning', 'Le paiement FedaPay n’est plus en attente. Consultez le statut de la facture.');
            }

            $checkoutUrl = $mobileMoneyManager->createCheckout($payment, $facture);
        } catch (MobileMoneyGatewayException $exception) {
            Log::warning('Impossible de reprendre le paiement FedaPay.', [
                'reference' => $payment->reference,
            ]);

            return back()->withErrors(['paiement' => $exception->getMessage()]);
        } catch (MobileMoneyIntegrityException $exception) {
            Log::warning('La reprise du paiement FedaPay a détecté une incohérence.', [
                'reference' => $payment->reference,
                'transaction_id' => $payment->external_reference,
            ]);

            return back()->withErrors(['paiement' => 'Les informations reçues ne correspondent pas à cette facture. Contactez le responsable de la boutique.']);
        }

        return redirect()->away($checkoutUrl);
    }

    public function refresh(
        Facture $facture,
        MobileMoneyManager $mobileMoneyManager,
        MobileMoneyPaymentService $mobileMoneyPaymentService,
    ): RedirectResponse {
        if ($facture->statut !== 'en_attente') {
            return back()->withErrors(['paiement' => 'Cette facture ne possède aucun paiement en attente.']);
        }

        $payment = $facture->paiementsMobileMoney()
            ->where('provider', 'fedapay')
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        if (! $payment) {
            return back()->withErrors(['paiement' => 'Aucun paiement FedaPay en attente n’a été trouvé.']);
        }

        if (! $payment->external_reference) {
            return back()->with('warning', 'La référence FedaPay est en cours de réconciliation. Le stock reste réservé.');
        }

        try {
            $transaction = $mobileMoneyManager->retrieveTransaction($payment->external_reference);
            $status = $mobileMoneyPaymentService->synchronize($payment, $transaction);
        } catch (MobileMoneyGatewayException $exception) {
            Log::warning('Impossible de vérifier le paiement FedaPay.', [
                'reference' => $payment->reference,
            ]);

            return back()->withErrors(['paiement' => $exception->getMessage()]);
        } catch (MobileMoneyIntegrityException $exception) {
            Log::warning('La vérification FedaPay a détecté une incohérence.', [
                'reference' => $payment->reference,
                'transaction_id' => $payment->external_reference,
            ]);

            return back()->withErrors(['paiement' => 'Les informations reçues ne correspondent pas à cette facture. Contactez le responsable de la boutique.']);
        }

        return match ($status) {
            'approved' => redirect()->route('factures.show', $facture)->with('success', 'Paiement FedaPay confirmé.'),
            'canceled', 'declined', 'failed' => redirect()->route('factures.show', $facture)->with('warning', 'Le paiement a échoué ou a été annulé. Le stock a été libéré.'),
            default => redirect()->route('factures.show', $facture)->with('warning', 'FedaPay indique que le paiement est toujours en attente.'),
        };
    }
}
