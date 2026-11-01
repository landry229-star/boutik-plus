<?php

namespace App\Http\Controllers;

use App\Models\Fournisseur;
use App\Services\TablePdfExporter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class FournisseurController extends Controller
{
    public function index(Request $request, TablePdfExporter $tablePdfExporter): View|Response
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'statut' => 'nullable|in:actif,archive,tous',
            'date_debut' => 'nullable|date_format:Y-m-d',
            'date_fin' => 'nullable|date_format:Y-m-d',
        ]);

        if (! empty($filters['date_debut']) && ! empty($filters['date_fin']) && $filters['date_debut'] > $filters['date_fin']) {
            throw ValidationException::withMessages([
                'date_debut' => 'La date de début doit être antérieure ou égale à la date de fin.',
            ]);
        }

        $query = Fournisseur::query()->withCount('achats');

        if (! empty($filters['q'])) {
            $search = $filters['q'];
            $query->where(function ($query) use ($search) {
                $query->where('nom', 'like', "%{$search}%")
                    ->orWhere('telephone', 'like', "%{$search}%")
                    ->orWhere('adresse', 'like', "%{$search}%");
            });
        }

        if (($filters['statut'] ?? 'actif') !== 'tous') {
            $query->where('actif', ($filters['statut'] ?? 'actif') === 'actif');
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
            $fournisseurs = $query->get();
            $criteria = array_filter([
                isset($filters['q']) ? 'Recherche : '.$filters['q'] : null,
                isset($filters['statut']) ? 'Statut : '.$filters['statut'] : 'Statut : actif',
                isset($filters['date_debut']) ? 'Créé du : '.$filters['date_debut'] : null,
                isset($filters['date_fin']) ? 'Créé au : '.$filters['date_fin'] : null,
            ]);

            return $tablePdfExporter->download('Liste des fournisseurs', 'fournisseurs', [[
                'title' => 'Fournisseurs ('.$fournisseurs->count().')',
                'headers' => ['Nom', 'Téléphone', 'Adresse', 'Achats effectués', 'Statut'],
                'rows' => $fournisseurs->map(fn (Fournisseur $fournisseur): array => [
                    $fournisseur->nom,
                    $fournisseur->telephone ?? '—',
                    $fournisseur->adresse ?? '—',
                    $fournisseur->achats_count,
                    $fournisseur->actif ? 'Actif' : 'Archivé',
                ])->all(),
            ]], array_values($criteria));
        }

        $fournisseurs = $query->paginate(25)->withQueryString();

        return view('fournisseurs.index', compact('fournisseurs', 'filters'));
    }

    public function create()
    {
        return view('fournisseurs.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => 'required|string|max:255',
            'telephone' => 'nullable|string|max:255',
            'adresse' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        Fournisseur::create($data);

        return redirect()->route('fournisseurs.index')->with('success', 'Fournisseur ajouté avec succès.');
    }

    public function edit(Fournisseur $fournisseur)
    {
        return view('fournisseurs.edit', compact('fournisseur'));
    }

    public function update(Request $request, Fournisseur $fournisseur)
    {
        $data = $request->validate([
            'nom' => 'required|string|max:255',
            'telephone' => 'nullable|string|max:255',
            'adresse' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $fournisseur->update($data);

        return redirect()->route('fournisseurs.index')->with('success', 'Fournisseur mis à jour.');
    }

    public function destroy(Fournisseur $fournisseur)
    {
        $fournisseur->update(['actif' => false]);

        return redirect()->route('fournisseurs.index')->with('success', 'Fournisseur archivé. Son historique d’achats est conservé.');
    }

    public function supprimerDefinitivement(Fournisseur $fournisseur): RedirectResponse
    {
        DB::transaction(function () use ($fournisseur) {
            $fournisseur = Fournisseur::query()->lockForUpdate()->findOrFail($fournisseur->id);

            if ($fournisseur->actif) {
                throw ValidationException::withMessages([
                    'fournisseur' => 'Archivez le fournisseur avant de le supprimer définitivement.',
                ]);
            }

            if ($fournisseur->achats()->exists()) {
                throw ValidationException::withMessages([
                    'fournisseur' => 'Ce fournisseur possède un historique d’achats et ne peut pas être supprimé.',
                ]);
            }

            $fournisseur->delete();
        }, 3);

        return redirect()->route('fournisseurs.index', ['statut' => 'archive'])->with('success', 'Fournisseur supprimé définitivement.');
    }

    public function activer(Fournisseur $fournisseur)
    {
        $fournisseur->update(['actif' => true]);

        return redirect()->route('fournisseurs.index')->with('success', 'Fournisseur réactivé.');
    }
}
