<?php

namespace App\Http\Controllers;

use App\Models\MouvementStock;
use App\Models\Produit;
use App\Services\TablePdfExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ProduitController extends Controller
{
    public function index(Request $request, TablePdfExporter $tablePdfExporter): View|Response
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'statut' => 'nullable|in:actif,archive,tous',
            'stock' => 'nullable|in:alerte,disponible',
            'date_debut' => 'nullable|date_format:Y-m-d',
            'date_fin' => 'nullable|date_format:Y-m-d',
        ]);

        if (! empty($filters['date_debut']) && ! empty($filters['date_fin']) && $filters['date_debut'] > $filters['date_fin']) {
            throw ValidationException::withMessages([
                'date_debut' => 'La date de début doit être antérieure ou égale à la date de fin.',
            ]);
        }

        $query = Produit::query();

        if (! $request->boolean('pdf')) {
            $query->withExists([
                'mouvementsStock',
                'factureLignes',
                'achatLignes',
            ]);
        }

        if (! empty($filters['q'])) {
            $search = $filters['q'];
            $query->where(function ($query) use ($search) {
                $query->where('nom', 'like', "%{$search}%")
                    ->orWhere('categorie', 'like', "%{$search}%")
                    ->orWhere('code_barre', 'like', "%{$search}%");
            });
        }

        if (($filters['statut'] ?? 'actif') !== 'tous') {
            $query->where('actif', ($filters['statut'] ?? 'actif') === 'actif');
        }

        if (($filters['stock'] ?? null) === 'alerte') {
            $query->whereColumn('quantite_stock', '<=', 'seuil_alerte');
        } elseif (($filters['stock'] ?? null) === 'disponible') {
            $query->whereColumn('quantite_stock', '>', 'seuil_alerte');
        }

        if (! empty($filters['date_debut'])) {
            $query->whereDate('created_at', '>=', $filters['date_debut']);
        }

        if (! empty($filters['date_fin'])) {
            $query->whereDate('created_at', '<=', $filters['date_fin']);
        }

        $query->orderByDesc('actif')->orderBy('nom');

        if ($request->boolean('pdf')) {
            $tablePdfExporter->assertQueryWithinLimit($query);
            $produits = $query->get();
            $criteria = array_filter([
                isset($filters['q']) ? 'Recherche : '.$filters['q'] : null,
                isset($filters['statut']) ? 'Catalogue : '.$filters['statut'] : 'Catalogue : actif',
                isset($filters['stock']) ? 'Stock : '.$filters['stock'] : null,
                isset($filters['date_debut']) ? 'Créé du : '.$filters['date_debut'] : null,
                isset($filters['date_fin']) ? 'Créé au : '.$filters['date_fin'] : null,
            ]);

            return $tablePdfExporter->download('Catalogue des produits', 'produits', [[
                'title' => 'Produits ('.$produits->count().')',
                'headers' => ['Nom', 'Catégorie', 'Prix de vente', 'Stock', 'Statut'],
                'rows' => $produits->map(fn (Produit $produit): array => [
                    $produit->nom,
                    $produit->categorie ?? '—',
                    number_format((float) $produit->prix_vente, 0, ',', ' ').' FCFA',
                    $produit->quantite_stock,
                    ! $produit->actif ? 'Archivé' : ($produit->estEnAlerte() ? 'Stock faible' : 'OK'),
                ])->all(),
            ]], array_values($criteria));
        }

        $produits = $query->paginate(25)->withQueryString();

        return view('produits.index', compact('produits', 'filters'));
    }

    public function create()
    {
        return view('produits.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => 'required|string|max:255',
            'categorie' => 'nullable|string|max:255',
            'code_barre' => 'nullable|string|max:255|unique:produits,code_barre',
            'prix_achat' => 'required|numeric|min:0',
            'prix_vente' => 'required|numeric|min:0',
            'quantite_stock' => 'required|integer|min:0',
            'seuil_alerte' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($data, $request) {
            $produit = Produit::create($data);

            if ($produit->quantite_stock > 0) {
                MouvementStock::create([
                    'produit_id' => $produit->id,
                    'user_id' => $request->user()->id,
                    'utilisateur_nom_snapshot' => $request->user()->name,
                    'type' => 'entree',
                    'quantite' => $produit->quantite_stock,
                    'motif' => 'Stock initial',
                ]);
            }
        });

        return redirect()->route('produits.index')->with('success', 'Produit ajouté avec succès.');
    }

    public function edit(Produit $produit)
    {
        return view('produits.edit', compact('produit'));
    }

    public function update(Request $request, Produit $produit)
    {
        $data = $request->validate([
            'nom' => 'required|string|max:255',
            'categorie' => 'nullable|string|max:255',
            'code_barre' => 'nullable|string|max:255|unique:produits,code_barre,'.$produit->id,
            'prix_achat' => 'required|numeric|min:0',
            'prix_vente' => 'required|numeric|min:0',
            'seuil_alerte' => 'required|integer|min:0',
        ]);

        $produit->update($data);

        return redirect()->route('produits.index')->with('success', 'Produit mis à jour.');
    }

    public function destroy(Produit $produit)
    {
        $produit->update(['actif' => false]);

        return redirect()->route('produits.index')->with('success', 'Produit archivé. Son historique est conservé.');
    }

    public function supprimerDefinitivement(Produit $produit): RedirectResponse
    {
        DB::transaction(function () use ($produit) {
            $produit = Produit::query()->lockForUpdate()->findOrFail($produit->id);

            if ($produit->actif) {
                throw ValidationException::withMessages([
                    'produit' => 'Archivez le produit avant de le supprimer définitivement.',
                ]);
            }

            $aUnHistorique = $produit->quantite_stock > 0
                || DB::table('mouvements_stock')->where('produit_id', $produit->id)->exists()
                || DB::table('facture_lignes')->where('produit_id', $produit->id)->exists()
                || DB::table('achat_lignes')->where('produit_id', $produit->id)->exists();

            if ($aUnHistorique) {
                throw ValidationException::withMessages([
                    'produit' => 'Ce produit possède un historique de stock, de vente ou d’achat et ne peut pas être supprimé.',
                ]);
            }

            $produit->delete();
        }, 3);

        return redirect()->route('produits.index', ['statut' => 'archive'])->with('success', 'Produit supprimé définitivement.');
    }

    public function activer(Produit $produit)
    {
        $produit->update(['actif' => true]);

        return redirect()->route('produits.index')->with('success', 'Produit réactivé.');
    }

    public function mouvement(Request $request, Produit $produit)
    {
        $data = $request->validate([
            'type' => 'required|in:entree,sortie',
            'quantite' => 'required|integer|min:1',
            'motif' => 'nullable|string|max:255',
        ]);

        $insuffisant = DB::transaction(function () use ($data, $produit, $request) {
            $produit = Produit::query()->lockForUpdate()->findOrFail($produit->id);

            if ($data['type'] === 'sortie' && $data['quantite'] > $produit->quantite_stock) {
                return true;
            }

            MouvementStock::create([
                'produit_id' => $produit->id,
                'user_id' => $request->user()->id,
                'utilisateur_nom_snapshot' => $request->user()->name,
                'type' => $data['type'],
                'quantite' => $data['quantite'],
                'motif' => $data['motif'] ?? null,
            ]);

            $produit->quantite_stock += $data['type'] === 'entree' ? $data['quantite'] : -$data['quantite'];
            $produit->save();

            return false;
        }, 3);

        if ($insuffisant) {
            return back()->withInput()->withErrors(['quantite' => 'Stock insuffisant pour cette sortie.']);
        }

        return redirect()->route('produits.index')->with('success', 'Mouvement de stock enregistré.');
    }
}
