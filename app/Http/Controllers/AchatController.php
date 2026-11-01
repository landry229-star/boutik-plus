<?php

namespace App\Http\Controllers;

use App\Models\Achat;
use App\Models\AchatLigne;
use App\Models\Fournisseur;
use App\Models\MouvementCaisse;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Services\TablePdfExporter;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AchatController extends Controller
{
    public function index(Request $request, TablePdfExporter $tablePdfExporter): View|Response
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'statut' => 'nullable|in:enregistree,annulee,tous',
            'fournisseur_id' => 'nullable|integer|exists:fournisseurs,id',
            'date_debut' => 'nullable|date_format:Y-m-d',
            'date_fin' => 'nullable|date_format:Y-m-d',
        ]);

        if (! empty($filters['date_debut']) && ! empty($filters['date_fin']) && $filters['date_debut'] > $filters['date_fin']) {
            throw ValidationException::withMessages([
                'date_debut' => 'La date de début doit être antérieure ou égale à la date de fin.',
            ]);
        }

        $query = Achat::with('fournisseur')
            ->withSum(['mouvementsCaisse as montant_paye' => fn ($mouvements) => $mouvements->where('type', 'sortie')], 'montant');

        if (! empty($filters['q'])) {
            $search = $filters['q'];
            $query->whereHas('fournisseur', fn ($fournisseurQuery) => $fournisseurQuery->where('nom', 'like', "%{$search}%"));
        }

        if (($filters['statut'] ?? 'tous') !== 'tous') {
            $query->where('statut', $filters['statut'] ?? 'enregistree');
        }

        if (! empty($filters['fournisseur_id'])) {
            $query->where('fournisseur_id', $filters['fournisseur_id']);
        }

        if (! empty($filters['date_debut'])) {
            $query->whereDate('date_achat', '>=', $filters['date_debut']);
        }

        if (! empty($filters['date_fin'])) {
            $query->whereDate('date_achat', '<=', $filters['date_fin']);
        }

        $query->orderByDesc('date_achat')->orderByDesc('id');

        if ($request->boolean('pdf')) {
            $tablePdfExporter->assertQueryWithinLimit($query);
            $achats = $query->get();
            $criteria = array_filter([
                isset($filters['q']) ? 'Recherche fournisseur : '.$filters['q'] : null,
                isset($filters['fournisseur_id']) ? 'Fournisseur : '.$filters['fournisseur_id'] : null,
                isset($filters['statut']) ? 'Statut : '.$filters['statut'] : 'Statut : tous',
                isset($filters['date_debut']) ? 'Du : '.$filters['date_debut'] : null,
                isset($filters['date_fin']) ? 'Au : '.$filters['date_fin'] : null,
            ]);

            return $tablePdfExporter->download('Liste des achats fournisseurs', 'achats-fournisseurs', [[
                'title' => 'Achats ('.$achats->count().')',
                'headers' => ['Date', 'Fournisseur', 'Montant total', 'Montant payé', 'Statut'],
                'rows' => $achats->map(fn (Achat $achat): array => [
                    Carbon::parse($achat->date_achat)->format('d/m/Y'),
                    $achat->fournisseur->nom,
                    number_format((float) $achat->montant_total, 0, ',', ' ').' FCFA',
                    number_format((float) ($achat->montant_paye ?? 0), 0, ',', ' ').' FCFA',
                    $achat->statut === 'annulee' ? 'Annulé' : 'Enregistré',
                ])->all(),
            ]], array_values($criteria));
        }

        $achats = $query->paginate(25)->withQueryString();
        $fournisseursFiltres = Fournisseur::query()->orderBy('nom')->get(['id', 'nom']);

        return view('achats.index', compact('achats', 'filters', 'fournisseursFiltres'));
    }

    public function create()
    {
        $fournisseurs = Fournisseur::where('actif', true)->orderBy('nom')->get();
        $produits = Produit::where('actif', true)->orderBy('nom')->get();

        return view('achats.create', compact('fournisseurs', 'produits'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'fournisseur_id' => 'required|exists:fournisseurs,id',
            'date_achat' => 'required|date',
            'notes' => 'nullable|string',
            'produits' => 'required|array|min:1',
            'produits.*.produit_id' => 'required|distinct|exists:produits,id',
            'produits.*.quantite' => 'required|integer|min:1',
            'produits.*.prix_achat_unitaire' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($data, $request) {
            $fournisseur = Fournisseur::query()->lockForUpdate()->findOrFail($data['fournisseur_id']);

            if (! $fournisseur->actif) {
                throw ValidationException::withMessages([
                    'fournisseur_id' => 'Ce fournisseur est archivé et ne peut pas être utilisé pour un nouvel achat.',
                ]);
            }

            $produitIds = collect($data['produits'])->pluck('produit_id')->sort()->values();
            $produits = Produit::query()
                ->whereIn('id', $produitIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($produits as $produit) {
                if (! $produit->actif) {
                    throw ValidationException::withMessages([
                        'produits' => 'Un produit sélectionné est archivé et ne peut pas être acheté.',
                    ]);
                }
            }

            $montantTotal = 0;
            foreach ($data['produits'] as $ligne) {
                $montantTotal += $ligne['quantite'] * $ligne['prix_achat_unitaire'];
            }

            $achat = Achat::create([
                'fournisseur_id' => $data['fournisseur_id'],
                'user_id' => $request->user()->id,
                'utilisateur_nom_snapshot' => $request->user()->name,
                'montant_total' => $montantTotal,
                'date_achat' => $data['date_achat'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['produits'] as $ligne) {
                AchatLigne::create([
                    'achat_id' => $achat->id,
                    'produit_id' => $ligne['produit_id'],
                    'quantite' => $ligne['quantite'],
                    'prix_achat_unitaire' => $ligne['prix_achat_unitaire'],
                ]);

                $produit = $produits->get($ligne['produit_id']);
                $produit->quantite_stock += $ligne['quantite'];
                $produit->prix_achat = $ligne['prix_achat_unitaire'];
                $produit->save();

                MouvementStock::create([
                    'produit_id' => $produit->id,
                    'user_id' => $request->user()->id,
                    'utilisateur_nom_snapshot' => $request->user()->name,
                    'achat_id' => $achat->id,
                    'type' => 'entree',
                    'quantite' => $ligne['quantite'],
                    'motif' => 'Achat fournisseur',
                ]);
            }
        }, 3);

        return redirect()->route('achats.index')->with('success', 'Achat enregistré, stock mis à jour.');
    }

    public function show(Achat $achat)
    {
        $achat->load('fournisseur', 'utilisateur', 'lignes.produit', 'annuleePar');
        $montantPaye = $achat->mouvementsCaisse()->where('type', 'sortie')->sum('montant');
        $montantDu = max(0, $achat->montant_total - $montantPaye);

        return view('achats.show', compact('achat', 'montantPaye', 'montantDu'));
    }

    public function paiement(Request $request, Achat $achat)
    {
        $data = $request->validate([
            'montant' => 'required|numeric|min:0.01',
            'moyen_paiement' => 'required|in:especes,mobile_money',
        ]);

        DB::transaction(function () use ($data, $achat, $request) {
            $achat = Achat::query()->lockForUpdate()->findOrFail($achat->id);

            if ($achat->statut === 'annulee') {
                throw ValidationException::withMessages([
                    'achat' => 'Un achat annulé ne peut plus recevoir de paiement.',
                ]);
            }

            $montantPaye = $achat->mouvementsCaisse()->where('type', 'sortie')->sum('montant');
            $montantDu = $achat->montant_total - $montantPaye;

            if ($data['montant'] > $montantDu) {
                throw ValidationException::withMessages([
                    'montant' => 'Le paiement dépasse le montant restant dû de '.number_format(max(0, $montantDu), 0, ',', ' ').' FCFA.',
                ]);
            }

            MouvementCaisse::create([
                'date_jour' => now()->toDateString(),
                'moyen_paiement' => $data['moyen_paiement'],
                'user_id' => $request->user()->id,
                'utilisateur_nom_snapshot' => $request->user()->name,
                'achat_id' => $achat->id,
                'type' => 'sortie',
                'montant' => $data['montant'],
                'description' => 'Paiement achat fournisseur #'.$achat->id,
            ]);
        }, 3);

        return redirect()->route('achats.show', $achat)->with('success', 'Paiement fournisseur enregistré dans la caisse.');
    }

    public function annuler(Request $request, Achat $achat): RedirectResponse
    {
        $data = $request->validate([
            'motif_annulation' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($data, $achat, $request) {
            $achat = Achat::query()->lockForUpdate()->findOrFail($achat->id);

            if ($achat->statut === 'annulee') {
                throw ValidationException::withMessages([
                    'achat' => 'Cet achat est déjà annulé.',
                ]);
            }

            if ($achat->mouvementsCaisse()->exists()) {
                throw ValidationException::withMessages([
                    'achat' => 'Cet achat comporte déjà un paiement. Il doit rester enregistré; rapprochez tout remboursement séparément sans supprimer son historique.',
                ]);
            }

            $lignes = $achat->lignes()->get();

            if ($lignes->isEmpty()) {
                throw ValidationException::withMessages([
                    'achat' => 'Cet achat ne contient aucune ligne et ne peut pas être annulé automatiquement.',
                ]);
            }

            $quantitesParProduit = $lignes
                ->groupBy('produit_id')
                ->map(fn ($lignes) => $lignes->sum('quantite'));
            $produits = Produit::query()
                ->whereIn('id', $quantitesParProduit->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($quantitesParProduit as $produitId => $quantite) {
                $produit = $produits->get($produitId);

                if (! $produit || $produit->quantite_stock < $quantite) {
                    throw ValidationException::withMessages([
                        'achat' => 'Le stock ne permet pas d’annuler cet achat sans devenir négatif. Vérifiez les retours et mouvements de stock.',
                    ]);
                }
            }

            foreach ($quantitesParProduit as $produitId => $quantite) {
                $produit = $produits->get($produitId);
                $produit->decrement('quantite_stock', $quantite);

                MouvementStock::create([
                    'produit_id' => $produit->id,
                    'user_id' => $request->user()->id,
                    'utilisateur_nom_snapshot' => $request->user()->name,
                    'achat_id' => $achat->id,
                    'type' => 'sortie',
                    'quantite' => $quantite,
                    'motif' => 'Annulation achat #'.$achat->id.' : '.$data['motif_annulation'],
                ]);
            }

            $achat->update([
                'statut' => 'annulee',
                'annulee_at' => now(),
                'annulee_par_user_id' => $request->user()->id,
                'annulee_par_nom_snapshot' => $request->user()->name,
                'motif_annulation' => $data['motif_annulation'],
            ]);
        }, 3);

        return redirect()->route('achats.show', $achat)->with('success', 'Achat annulé; le stock et son historique ont été corrigés.');
    }

    public function supprimerDefinitivement(Achat $achat): RedirectResponse
    {
        DB::transaction(function () use ($achat): void {
            $achat = Achat::query()->lockForUpdate()->findOrFail($achat->id);

            if ($achat->statut !== 'annulee') {
                throw ValidationException::withMessages([
                    'achat' => 'Annulez l’achat avant de le supprimer définitivement.',
                ]);
            }

            if ($achat->mouvementsCaisse()->exists()) {
                throw ValidationException::withMessages([
                    'achat' => 'Cet achat possède des mouvements de caisse liés et ne peut pas être supprimé.',
                ]);
            }

            DB::table('mouvements_stock')->where('achat_id', $achat->id)->delete();
            $achat->lignes()->delete();
            $achat->delete();
        }, 3);

        return redirect()->route('achats.index')->with('success', 'Achat annulé et données liées supprimées définitivement.');
    }
}
