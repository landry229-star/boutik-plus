<?php

namespace App\Http\Controllers;

use App\Models\Facture;
use App\Models\FactureLigne;
use App\Models\Produit;
use App\Services\TablePdfExporter;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends Controller
{
    public function index(Request $request, TablePdfExporter $tablePdfExporter): View|Response
    {
        $aujourdHui = now()->toDateString();
        $debutMois = now()->startOfMonth()->toDateString();
        $periode = $request->validate([
            'date_debut' => 'nullable|date_format:Y-m-d',
            'date_fin' => 'nullable|date_format:Y-m-d',
            'table' => 'nullable|in:paiements,top_produits,alertes_stock',
        ]);
        $dateDebut = $periode['date_debut'] ?? $debutMois;
        $dateFin = $periode['date_fin'] ?? $aujourdHui;

        if ($dateDebut > $dateFin) {
            throw ValidationException::withMessages([
                'date_debut' => 'La date de début doit être antérieure ou égale à la date de fin.',
            ]);
        }

        $isPdf = $request->boolean('pdf');
        $table = $periode['table'] ?? null;

        // Chiffre d'affaires du jour et du mois
        $ventesJour = Facture::where('statut', 'payee')->whereDate('created_at', $aujourdHui)->sum('montant_total');
        $ventesMois = Facture::where('statut', 'payee')->whereDate('created_at', '>=', $debutMois)->sum('montant_total');

        $nombreFacturesJour = Facture::where('statut', 'payee')->whereDate('created_at', $aujourdHui)->count();

        $ventesPeriode = Facture::where('statut', 'payee')
            ->whereDate('created_at', '>=', $dateDebut)
            ->whereDate('created_at', '<=', $dateFin);
        $montantPeriode = (clone $ventesPeriode)->sum('montant_total');
        $nombreFacturesPeriode = (clone $ventesPeriode)->count();
        $ventesParPaiement = (clone $ventesPeriode)
            ->select('mode_paiement', DB::raw('SUM(montant_total) as montant'))
            ->groupBy('mode_paiement')
            ->get()
            ->keyBy('mode_paiement');

        $topProduits = FactureLigne::select(
            'facture_lignes.produit_id',
            DB::raw('SUM(facture_lignes.quantite) as total_quantite'),
            DB::raw('SUM(facture_lignes.sous_total) as total_montant')
        )
            ->join('factures', 'factures.id', '=', 'facture_lignes.facture_id')
            ->where('factures.statut', 'payee')
            ->whereDate('factures.created_at', '>=', $dateDebut)
            ->whereDate('factures.created_at', '<=', $dateFin)
            ->groupBy('facture_lignes.produit_id')
            ->orderByDesc('total_quantite')
            ->limit(5)
            ->get();

        // On charge les produits correspondants en une seule requete (evite le probleme N+1)
        $produitsParId = $topProduits->isEmpty()
            ? collect()
            : Produit::whereIn('id', $topProduits->pluck('produit_id'))->get()->keyBy('id');
        foreach ($topProduits as $ligne) {
            $ligne->produit = $produitsParId->get($ligne->produit_id);
        }

        $produitsEnAlerteQuery = Produit::whereColumn('quantite_stock', '<=', 'seuil_alerte');
        if (! $isPdf) {
            $produitsEnAlerte = $produitsEnAlerteQuery->get();
        } elseif ($table === null || $table === 'alertes_stock') {
            $additionalRows = $table === null ? 2 + $topProduits->count() : 0;
            $tablePdfExporter->assertQueryWithinLimit($produitsEnAlerteQuery, $additionalRows);
            $produitsEnAlerte = $produitsEnAlerteQuery->get();
        } else {
            $produitsEnAlerte = collect();
        }

        $ventes7Jours = [];

        if (! $isPdf) {
            for ($i = 6; $i >= 0; $i--) {
                $jour = now()->subDays($i);
                $ventes7Jours[] = [
                    'jour' => $jour->translatedFormat('d/m'),
                    'montant' => (float) Facture::where('statut', 'payee')->whereDate('created_at', $jour->toDateString())->sum('montant_total'),
                ];
            }
        }

        if ($isPdf) {
            $reportSections = [
                [
                    'key' => 'paiements',
                    'title' => 'Ventes par moyen de paiement',
                    'headers' => ['Moyen de paiement', 'Montant'],
                    'rows' => [
                        ['Espèces', number_format((float) ($ventesParPaiement->get('especes')?->montant ?? 0), 0, ',', ' ').' FCFA'],
                        ['Mobile Money', number_format((float) ($ventesParPaiement->get('mobile_money')?->montant ?? 0), 0, ',', ' ').' FCFA'],
                    ],
                ],
                [
                    'key' => 'top_produits',
                    'title' => 'Top 5 des produits sur la période choisie',
                    'headers' => ['Produit', 'Quantité vendue', 'Montant généré'],
                    'rows' => $topProduits->map(fn (FactureLigne $ligne): array => [
                        $ligne->produit->nom ?? 'Produit supprimé',
                        $ligne->total_quantite,
                        number_format((float) $ligne->total_montant, 0, ',', ' ').' FCFA',
                    ])->all(),
                ],
                [
                    'key' => 'alertes_stock',
                    'title' => 'Produits à réapprovisionner',
                    'headers' => ['Produit', 'Stock restant', 'Seuil d’alerte'],
                    'rows' => $produitsEnAlerte->map(fn (Produit $produit): array => [
                        $produit->nom,
                        $produit->quantite_stock,
                        $produit->seuil_alerte,
                    ])->all(),
                ],
            ];
            if ($table !== null) {
                $reportSections = array_values(array_filter(
                    $reportSections,
                    fn (array $section): bool => $section['key'] === $table,
                ));
            }

            return $tablePdfExporter->download('Rapport du tableau de bord', 'rapport-tableau-de-bord', array_map(
                fn (array $section): array => [
                    'title' => $section['title'],
                    'headers' => $section['headers'],
                    'rows' => $section['rows'],
                ],
                $reportSections,
            ), [
                'Période : '.Carbon::parse($dateDebut)->format('d/m/Y').' au '.Carbon::parse($dateFin)->format('d/m/Y'),
                'Ventes payées : '.number_format((float) $montantPeriode, 0, ',', ' ').' FCFA',
                'Factures payées : '.$nombreFacturesPeriode,
            ]);
        }

        return view('dashboard.index', compact(
            'ventesJour',
            'ventesMois',
            'nombreFacturesJour',
            'dateDebut',
            'dateFin',
            'montantPeriode',
            'nombreFacturesPeriode',
            'ventesParPaiement',
            'topProduits',
            'produitsEnAlerte',
            'ventes7Jours'
        ));
    }
}
