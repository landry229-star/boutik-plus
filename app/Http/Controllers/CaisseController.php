<?php

namespace App\Http\Controllers;

use App\Models\MouvementCaisse;
use App\Services\TablePdfExporter;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class CaisseController extends Controller
{
    public function index(Request $request, TablePdfExporter $tablePdfExporter): View|Response
    {
        $data = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'type' => 'nullable|in:entree,sortie',
            'moyen_paiement' => 'nullable|in:especes,mobile_money',
            'q' => 'nullable|string|max:100',
        ]);
        $date = $data['date'] ?? now()->toDateString();

        $mouvementsQuery = MouvementCaisse::query()
            ->with(['annulation', 'mouvementOrigine'])
            ->whereDate('date_jour', $date);

        if (! empty($data['type'])) {
            $mouvementsQuery->where('type', $data['type']);
        }

        if (! empty($data['moyen_paiement'])) {
            $mouvementsQuery->where('moyen_paiement', $data['moyen_paiement']);
        }

        if (! empty($data['q'])) {
            $mouvementsQuery->where('description', 'like', '%'.$data['q'].'%');
        }

        $mouvementsQuery->orderBy('created_at');

        if ($request->boolean('pdf')) {
            $tablePdfExporter->assertQueryWithinLimit($mouvementsQuery);
            $mouvements = $mouvementsQuery->get();
            $criteria = array_filter([
                'Journée : '.Carbon::parse($date)->format('d/m/Y'),
                isset($data['type']) ? 'Type : '.$data['type'] : null,
                isset($data['moyen_paiement']) ? 'Paiement : '.$data['moyen_paiement'] : null,
                isset($data['q']) ? 'Description : '.$data['q'] : null,
            ]);

            return $tablePdfExporter->download('Journal de caisse', 'journal-de-caisse', [[
                'title' => 'Mouvements ('.$mouvements->count().')',
                'headers' => ['Heure', 'Type', 'Moyen', 'Montant', 'Description', 'État'],
                'rows' => $mouvements->map(fn (MouvementCaisse $mouvement): array => [
                    $mouvement->created_at->format('H:i'),
                    $mouvement->type === 'entree' ? 'Entrée' : 'Sortie',
                    $mouvement->moyen_paiement === 'mobile_money' ? 'Mobile Money' : 'Espèces',
                    number_format((float) $mouvement->montant, 0, ',', ' ').' FCFA',
                    $mouvement->description ?? '—',
                    $mouvement->mouvement_origine_id ? 'Correction de #'.$mouvement->mouvement_origine_id : ($mouvement->annulation ? 'Annulé' : 'Enregistré'),
                ])->all(),
            ]], array_values($criteria));
        }

        $mouvements = $mouvementsQuery->paginate(50)->withQueryString();

        $totaux = MouvementCaisse::query()
            ->whereDate('date_jour', $date)
            ->selectRaw("COALESCE(SUM(CASE WHEN moyen_paiement = 'especes' AND type = 'entree' THEN montant ELSE 0 END), 0) as entrees_especes")
            ->selectRaw("COALESCE(SUM(CASE WHEN moyen_paiement = 'especes' AND type = 'sortie' THEN montant ELSE 0 END), 0) as sorties_especes")
            ->selectRaw("COALESCE(SUM(CASE WHEN moyen_paiement = 'mobile_money' AND type = 'entree' THEN montant WHEN moyen_paiement = 'mobile_money' AND type = 'sortie' THEN -montant ELSE 0 END), 0) as solde_mobile_money")
            ->first();
        $totalEntrees = (float) $totaux->entrees_especes;
        $totalSorties = (float) $totaux->sorties_especes;
        $totalMobileMoney = (float) $totaux->solde_mobile_money;
        $solde = $totalEntrees - $totalSorties;

        return view('caisse.index', [
            'mouvements' => $mouvements,
            'date' => $date,
            'filters' => $data,
            'totalEntrees' => $totalEntrees,
            'totalSorties' => $totalSorties,
            'totalMobileMoney' => $totalMobileMoney,
            'solde' => $solde,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:entree,sortie',
            'moyen_paiement' => 'required|in:especes,mobile_money',
            'montant' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        MouvementCaisse::create([
            'date_jour' => now()->toDateString(),
            'moyen_paiement' => $data['moyen_paiement'],
            'user_id' => $request->user()->id,
            'utilisateur_nom_snapshot' => $request->user()->name,
            'type' => $data['type'],
            'montant' => $data['montant'],
            'description' => $data['description'] ?? null,
        ]);

        return redirect()->route('caisse.index')->with('success', 'Mouvement de caisse enregistré.');
    }

    public function annuler(Request $request, MouvementCaisse $mouvement): RedirectResponse
    {
        DB::transaction(function () use ($request, $mouvement) {
            $mouvement = MouvementCaisse::query()->lockForUpdate()->findOrFail($mouvement->id);

            if ($mouvement->facture_id !== null || $mouvement->achat_id !== null) {
                throw ValidationException::withMessages([
                    'mouvement' => 'Les mouvements liés à une facture ou un achat doivent être corrigés depuis leur document d’origine.',
                ]);
            }

            if ($mouvement->mouvement_origine_id !== null
                || MouvementCaisse::query()->where('mouvement_origine_id', $mouvement->id)->exists()) {
                throw ValidationException::withMessages([
                    'mouvement' => 'Ce mouvement a déjà été annulé ou constitue une écriture de correction.',
                ]);
            }

            MouvementCaisse::create([
                'date_jour' => $mouvement->date_jour,
                'moyen_paiement' => $mouvement->moyen_paiement,
                'user_id' => $request->user()->id,
                'utilisateur_nom_snapshot' => $request->user()->name,
                'mouvement_origine_id' => $mouvement->id,
                'type' => $mouvement->type === 'entree' ? 'sortie' : 'entree',
                'montant' => $mouvement->montant,
                'description' => 'Annulation du mouvement de caisse #'.$mouvement->id,
            ]);
        }, 3);

        return redirect()->route('caisse.index', ['date' => $mouvement->date_jour])->with('success', 'Mouvement annulé par une écriture inverse; l’historique est conservé.');
    }

    public function supprimerDefinitivement(MouvementCaisse $mouvement): RedirectResponse
    {
        DB::transaction(function () use ($mouvement): void {
            $mouvement = MouvementCaisse::query()->lockForUpdate()->findOrFail($mouvement->id);

            if ($mouvement->facture_id !== null || $mouvement->achat_id !== null) {
                throw ValidationException::withMessages([
                    'mouvement' => 'Un mouvement lié à une facture ou un achat doit être supprimé depuis son document d’origine.',
                ]);
            }

            if ($mouvement->mouvement_origine_id !== null) {
                throw ValidationException::withMessages([
                    'mouvement' => 'Supprimez le mouvement d’origine pour retirer ensemble son écriture d’annulation.',
                ]);
            }

            $annulation = MouvementCaisse::query()
                ->where('mouvement_origine_id', $mouvement->id)
                ->lockForUpdate()
                ->first();

            if (! $annulation) {
                throw ValidationException::withMessages([
                    'mouvement' => 'Annulez ce mouvement avant de le supprimer définitivement.',
                ]);
            }

            $annulation->delete();
            $mouvement->delete();
        }, 3);

        return redirect()->route('caisse.index', ['date' => $mouvement->date_jour])
            ->with('success', 'Mouvement annulé et écriture inverse supprimés définitivement.');
    }
}
