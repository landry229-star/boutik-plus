<?php

namespace App\Http\Controllers;

use App\Models\Achat;
use App\Services\PdfBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AchatPdfController extends Controller
{
    public function download(Achat $achat, PdfBranding $branding): Response
    {
        $achat->load(['fournisseur', 'utilisateur', 'lignes.produit', 'annuleePar']);
        $paiements = $achat->mouvementsCaisse()
            ->where('type', 'sortie')
            ->oldest('created_at')
            ->get(['id', 'created_at', 'moyen_paiement', 'montant']);
        $montantPaye = (float) $paiements->sum('montant');

        return Pdf::loadView('achats.pdf', [
            'achat' => $achat,
            'paiements' => $paiements,
            'montantPaye' => $montantPaye,
            'montantDu' => max(0, (float) $achat->montant_total - $montantPaye),
            ...$branding->viewData(),
        ])
            ->setPaper('a4')
            ->download(Str::slug('achat-'.$achat->id).'.pdf');
    }
}
