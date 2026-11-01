<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use App\Services\PdfBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ProduitPdfController extends Controller
{
    public function download(Produit $produit, PdfBranding $branding): Response
    {
        return Pdf::loadView('produits.pdf', [
            'produit' => $produit,
            'generatedAt' => now(),
            ...$branding->viewData(),
        ])
            ->setPaper('a4')
            ->download(Str::slug('fiche-produit-'.$produit->nom).'.pdf');
    }
}
