<?php

namespace App\Http\Controllers;

use App\Mail\FactureMail;
use App\Models\Facture;
use App\Models\FactureLigne;
use App\Models\MobileMoneyPayment;
use App\Models\MouvementCaisse;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Services\MobileMoney\MobileMoneyGatewayException;
use App\Services\MobileMoney\MobileMoneyManager;
use App\Services\MobileMoney\MobileMoneyPaymentService;
use App\Services\PdfBranding;
use App\Services\TablePdfExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class FactureController extends Controller
{
    public function index(Request $request, TablePdfExporter $tablePdfExporter): View|Response
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'statut' => 'nullable|in:payee,en_attente,annulee,tous',
            'moyen_paiement' => 'nullable|in:especes,mobile_money',
            'date_debut' => 'nullable|date_format:Y-m-d',
            'date_fin' => 'nullable|date_format:Y-m-d',
        ]);

        if (! empty($filters['date_debut']) && ! empty($filters['date_fin']) && $filters['date_debut'] > $filters['date_fin']) {
            throw ValidationException::withMessages([
                'date_debut' => 'La date de début doit être antérieure ou égale à la date de fin.',
            ]);
        }

        $query = Facture::query()
            ->select([
                'id',
                'numero',
                'client_nom',
                'montant_total',
                'mode_paiement',
                'statut',
                'user_id',
                'utilisateur_nom_snapshot',
                'created_at',
            ])
            ->with([
                'utilisateur:id,name',
                'paiementsMobileMoney:id,facture_id,provider,status',
            ]);

        if (! empty($filters['q'])) {
            $search = $filters['q'];
            $query->where(function ($query) use ($search) {
                $query->where('numero', 'like', "%{$search}%")
                    ->orWhere('client_nom', 'like', "%{$search}%");
            });
        }

        if (($filters['statut'] ?? 'tous') !== 'tous') {
            $query->where('statut', $filters['statut']);
        }

        if (! empty($filters['moyen_paiement'])) {
            $query->where('mode_paiement', $filters['moyen_paiement']);
        }

        if (! empty($filters['date_debut'])) {
            $query->whereDate('created_at', '>=', $filters['date_debut']);
        }

        if (! empty($filters['date_fin'])) {
            $query->whereDate('created_at', '<=', $filters['date_fin']);
        }

        $query->orderByDesc('created_at');

        if ($request->boolean('pdf')) {
            $tablePdfExporter->assertQueryWithinLimit($query);
            $factures = $query->get();
            $criteria = array_filter([
                isset($filters['q']) ? 'Recherche : '.$filters['q'] : null,
                isset($filters['statut']) ? 'Statut : '.$filters['statut'] : 'Statut : tous',
                isset($filters['moyen_paiement']) ? 'Paiement : '.$filters['moyen_paiement'] : null,
                isset($filters['date_debut']) ? 'Du : '.$filters['date_debut'] : null,
                isset($filters['date_fin']) ? 'Au : '.$filters['date_fin'] : null,
            ]);

            return $tablePdfExporter->download('Liste des factures', 'factures', [[
                'title' => 'Factures ('.$factures->count().')',
                'headers' => ['Numéro', 'Client', 'Montant total', 'Paiement', 'Statut', 'Vendeur', 'Date'],
                'rows' => $factures->map(fn (Facture $facture): array => [
                    $facture->numero,
                    $facture->client_nom ?? 'Client comptant',
                    number_format((float) $facture->montant_total, 0, ',', ' ').' FCFA',
                    $facture->mode_paiement === 'mobile_money' ? 'Mobile Money' : 'Espèces',
                    match ($facture->statut) {
                        'annulee' => 'Annulée',
                        'en_attente' => 'Paiement en attente',
                        default => 'Payée',
                    },
                    $facture->utilisateur->name ?? $facture->utilisateur_nom_snapshot ?? '—',
                    $facture->created_at->format('d/m/Y H:i'),
                ])->all(),
            ]], array_values($criteria));
        }

        $factures = $query->paginate(25)->withQueryString();

        return view('factures.index', compact('factures', 'filters'));
    }

    public function create(MobileMoneyManager $mobileMoneyManager)
    {
        $produits = Produit::where('actif', true)->where('quantite_stock', '>', 0)->orderBy('nom')->get();

        return view('factures.create', [
            'produits' => $produits,
            'fedapayAvailable' => $mobileMoneyManager->isAvailable(),
        ]);
    }

    public function store(
        Request $request,
        MobileMoneyManager $mobileMoneyManager,
        MobileMoneyPaymentService $mobileMoneyPaymentService,
    ): RedirectResponse {
        $data = $request->validate([
            'client_nom' => 'nullable|string|max:255',
            'client_telephone' => 'nullable|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'mode_paiement' => 'required|in:especes,mobile_money,fedapay',
            'produits' => 'required|array|min:1',
            'produits.*.id' => 'required|exists:produits,id',
            'produits.*.quantite' => 'required|integer|min:1',
        ]);

        $isFedaPayPayment = $data['mode_paiement'] === 'fedapay';

        if ($isFedaPayPayment && ! $mobileMoneyManager->isAvailable()) {
            throw ValidationException::withMessages([
                'mode_paiement' => 'Le paiement FedaPay n’est pas activé ou ses clés ne sont pas configurées.',
            ]);
        }

        $result = DB::transaction(function () use ($data, $request, $isFedaPayPayment): array {
            $quantitesParProduit = collect($data['produits'])
                ->groupBy('id')
                ->map(fn ($lignes) => $lignes->sum('quantite'));

            $produits = Produit::query()
                ->whereIn('id', $quantitesParProduit->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($quantitesParProduit as $produitId => $quantite) {
                $produit = $produits->get($produitId);

                if (! $produit || ! $produit->actif) {
                    throw ValidationException::withMessages([
                        'produits' => 'Un produit sélectionné n’est plus disponible.',
                    ]);
                }

                if ($quantite > $produit->quantite_stock) {
                    throw ValidationException::withMessages([
                        'produits' => 'Stock insuffisant pour '.$produit->nom.' (disponible : '.$produit->quantite_stock.').',
                    ]);
                }
            }

            $facture = Facture::create([
                'user_id' => $request->user()->id,
                'utilisateur_nom_snapshot' => $request->user()->name,
                'numero' => 'FAC-'.strtoupper(Str::random(8)),
                'client_nom' => $data['client_nom'] ?? null,
                'client_telephone' => $data['client_telephone'] ?? null,
                'client_email' => $data['client_email'] ?? null,
                'mode_paiement' => $isFedaPayPayment ? 'mobile_money' : $data['mode_paiement'],
                'statut' => $isFedaPayPayment ? 'en_attente' : 'payee',
                'montant_total' => 0,
            ]);

            $total = 0;

            foreach ($quantitesParProduit as $produitId => $quantite) {
                $produit = $produits->get($produitId);
                $sousTotal = $produit->prix_vente * $quantite;
                $total += $sousTotal;

                FactureLigne::create([
                    'facture_id' => $facture->id,
                    'produit_id' => $produit->id,
                    'quantite' => $quantite,
                    'prix_unitaire' => $produit->prix_vente,
                    'sous_total' => $sousTotal,
                ]);

                $produit->decrement('quantite_stock', $quantite);

                MouvementStock::create([
                    'produit_id' => $produit->id,
                    'user_id' => $request->user()->id,
                    'utilisateur_nom_snapshot' => $request->user()->name,
                    'facture_id' => $facture->id,
                    'type' => 'sortie',
                    'quantite' => $quantite,
                    'motif' => 'Vente facture '.$facture->numero,
                ]);
            }

            $montantHt = round($total, 2);
            $taxeActivee = config('boutik.tax.enabled');
            $tauxTaxe = round((float) config('boutik.tax.rate', 0), 2);

            if ($taxeActivee && (! is_finite($tauxTaxe) || $tauxTaxe <= 0 || $tauxTaxe > 999.99)) {
                throw new \LogicException('La taxe est activée, mais son taux doit être compris entre 0 et 999,99 %.');
            }

            $montantTaxe = $taxeActivee ? round($montantHt * $tauxTaxe / 100, 2) : 0;
            $montantTotal = round($montantHt + $montantTaxe, 2);

            if ($isFedaPayPayment && ($montantTotal <= 0 || $montantTotal !== round($montantTotal))) {
                throw ValidationException::withMessages([
                    'mode_paiement' => 'FedaPay en XOF exige un montant total positif en nombre entier de francs CFA.',
                ]);
            }

            $facture->update([
                'montant_total' => $montantTotal,
                'montant_ht' => $montantHt,
                'montant_taxe' => $montantTaxe,
                'taux_taxe' => $taxeActivee ? $tauxTaxe : 0,
                'libelle_taxe' => $taxeActivee ? config('boutik.tax.label') : null,
            ]);

            $payment = null;

            if ($isFedaPayPayment) {
                $payment = MobileMoneyPayment::create([
                    'facture_id' => $facture->id,
                    'provider' => 'fedapay',
                    'status' => 'pending',
                    'reference' => 'MM-'.Str::uuid(),
                    'montant' => $montantTotal,
                    'devise' => config('mobile_money.currency'),
                    'operation' => 'collect',
                    'payload' => ['facture_numero' => $facture->numero],
                ]);
            } else {
                MouvementCaisse::create([
                    'date_jour' => now()->toDateString(),
                    'moyen_paiement' => $data['mode_paiement'],
                    'user_id' => $request->user()->id,
                    'utilisateur_nom_snapshot' => $request->user()->name,
                    'facture_id' => $facture->id,
                    'type' => 'entree',
                    'montant' => $montantTotal,
                    'description' => 'Vente facture '.$facture->numero,
                ]);
            }

            return ['facture' => $facture, 'paiement' => $payment];
        }, 3);

        /** @var Facture $facture */
        $facture = $result['facture'];

        if ($result['paiement'] instanceof MobileMoneyPayment) {
            try {
                $checkoutUrl = $mobileMoneyManager->createCheckout($result['paiement'], $facture);
            } catch (MobileMoneyGatewayException $exception) {
                Log::warning('La création du paiement FedaPay a échoué ou reste incertaine.', [
                    'reference' => $result['paiement']->reference,
                    'reservation_may_be_released' => $exception->safeToReleaseReservation,
                ]);

                if ($exception->safeToReleaseReservation) {
                    $mobileMoneyPaymentService->rejectBeforeCheckout($result['paiement']);

                    return redirect()->route('factures.show', $facture)
                        ->withErrors(['paiement' => $exception->getMessage()]);
                }

                return redirect()->route('factures.show', $facture)
                    ->with('warning', $exception->getMessage());
            }

            return redirect()->away($checkoutUrl);
        }

        return redirect()->route('factures.show', $facture)->with('success', 'Facture créée avec succès.');
    }

    public function show(Facture $facture, PdfBranding $branding)
    {
        $facture->load('lignes.produit', 'utilisateur', 'paiementsMobileMoney');

        return view('factures.show', [
            'facture' => $facture,
            'company' => $branding->companyData(),
            'currency' => config('boutik.currency'),
        ]);
    }

    public function envoyerEmail(Facture $facture)
    {
        if ($facture->statut !== 'payee') {
            return back()->withErrors(['facture' => 'Seule une facture payée peut être envoyée comme facture valide.']);
        }

        if (! $facture->client_email) {
            return back()->withErrors(['client_email' => "Aucun email n'est enregistré pour cette facture."]);
        }

        $facture->load('lignes.produit');

        Mail::to($facture->client_email)->send(new FactureMail($facture));

        return back()->with('success', 'Facture envoyée par email à '.$facture->client_email);
    }

    public function annuler(Request $request, Facture $facture)
    {
        $data = $request->validate([
            'motif_annulation' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($data, $facture, $request) {
            $facture = Facture::query()->lockForUpdate()->findOrFail($facture->id);

            if ($facture->statut !== 'payee') {
                throw ValidationException::withMessages([
                    'facture' => $facture->statut === 'en_attente'
                        ? 'Le paiement FedaPay est encore en attente. La facture ne peut pas être annulée avant sa confirmation ou son échec.'
                        : 'Cette facture est déjà annulée.',
                ]);
            }

            if ($facture->paiementsMobileMoney()
                ->where('provider', 'fedapay')
                ->where('status', 'approved')
                ->exists()) {
                throw ValidationException::withMessages([
                    'facture' => 'Les remboursements FedaPay ne sont pas intégrés. Cette facture ne peut pas être annulée dans Boutik Plus.',
                ]);
            }

            $lignes = $facture->lignes()->get();
            $quantitesParProduit = $lignes->groupBy('produit_id')
                ->map(fn ($lignes) => $lignes->sum('quantite'));
            $produits = Produit::query()
                ->whereIn('id', $quantitesParProduit->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($quantitesParProduit as $produitId => $quantite) {
                $produit = $produits->get($produitId);

                if (! $produit) {
                    throw ValidationException::withMessages([
                        'facture' => 'Un produit de cette facture est introuvable; annulation interrompue.',
                    ]);
                }

                $produit->increment('quantite_stock', $quantite);

                MouvementStock::create([
                    'produit_id' => $produit->id,
                    'user_id' => $request->user()->id,
                    'utilisateur_nom_snapshot' => $request->user()->name,
                    'facture_id' => $facture->id,
                    'type' => 'entree',
                    'quantite' => $quantite,
                    'motif' => 'Annulation facture '.$facture->numero.' : '.$data['motif_annulation'],
                ]);
            }

            MouvementCaisse::create([
                'date_jour' => now()->toDateString(),
                'moyen_paiement' => $facture->mode_paiement,
                'user_id' => $request->user()->id,
                'facture_id' => $facture->id,
                'type' => 'sortie',
                'montant' => $facture->montant_total,
                'description' => 'Annulation facture '.$facture->numero.' : '.$data['motif_annulation'],
            ]);

            $facture->update([
                'statut' => 'annulee',
                'annulee_at' => now(),
                'annulee_par_user_id' => $request->user()->id,
                'annulee_par_nom_snapshot' => $request->user()->name,
                'motif_annulation' => $data['motif_annulation'],
            ]);
        }, 3);

        return redirect()->route('factures.show', $facture)->with('success', 'Facture annulée; le stock et le journal de caisse ont été corrigés.');
    }

    public function supprimerDefinitivement(Facture $facture): RedirectResponse
    {
        DB::transaction(function () use ($facture): void {
            $facture = Facture::query()->lockForUpdate()->findOrFail($facture->id);

            if ($facture->statut !== 'annulee') {
                throw ValidationException::withMessages([
                    'facture' => 'Annulez la facture avant de la supprimer définitivement.',
                ]);
            }

            DB::table('mobile_money_payments')->where('facture_id', $facture->id)->delete();
            DB::table('mouvements_caisse')->where('facture_id', $facture->id)->delete();
            DB::table('mouvements_stock')->where('facture_id', $facture->id)->delete();
            $facture->lignes()->delete();
            $facture->delete();
        }, 3);

        return redirect()->route('factures.index')->with('success', 'Facture annulée et données liées supprimées définitivement.');
    }
}
