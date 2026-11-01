<?php

namespace App\Http\Controllers;

use App\Models\Facture;
use App\Services\PdfBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class FacturePdfController extends Controller
{
    public function download(Request $request, Facture $facture, PdfBranding $branding): Response
    {
        $data = $request->validate([
            'format' => ['sometimes', 'in:simple,complet'],
        ]);

        $facture->load('lignes.produit', 'utilisateur');

        return Pdf::loadView('factures.pdf', [
            'facture' => $facture,
            'format' => $data['format'] ?? 'complet',
            ...$branding->viewData(),
        ])
            ->setPaper('a4')
            ->download(Str::slug($facture->numero).'.pdf');
    }
}
