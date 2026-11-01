<!DOCTYPE html>
<html lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Fiche produit {{ $produit->nom }}</title>
    <style>
        @page { margin: 34px 38px; }
        body { color: #26332b; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.6; }
        table { border-collapse: collapse; width: 100%; }
        .header { border-bottom: 2px solid #1b7a3d; margin-bottom: 24px; padding-bottom: 16px; }
        .header td { vertical-align: top; }
        .logo-cell { padding-right: 14px; width: 112px; }
        .logo { max-height: 70px; max-width: 105px; }
        .company-name { color: #1b7a3d; font-size: 19px; font-weight: bold; margin-bottom: 4px; }
        .company-details { color: #536159; }
        .document-kind { color: #1b7a3d; font-size: 20px; font-weight: bold; text-align: right; }
        .document-number { color: #536159; text-align: right; }
        .product-name { color: #1b7a3d; font-size: 22px; font-weight: bold; margin: 0 0 4px; }
        .muted { color: #65716a; }
        .status { background: #edf6ef; color: #1b7a3d; font-weight: bold; padding: 4px 8px; }
        .archived { background: #f0f1f2; color: #59616a; font-weight: bold; padding: 4px 8px; }
        .section-title { border-bottom: 1px solid #d9dfdb; color: #1b7a3d; font-size: 11px; font-weight: bold; margin: 22px 0 10px; padding-bottom: 5px; text-transform: uppercase; }
        .details td { border-bottom: 1px solid #e1e7e3; padding: 9px 8px; width: 50%; }
        .details td:first-child { color: #65716a; }
        .notice { border-top: 1px solid #d9dfdb; color: #65716a; margin-top: 30px; padding-top: 10px; text-align: center; }
    </style>
</head>
<body>
    @include('pdf.partials.company-header', [
        'documentTitle' => 'FICHE PRODUIT',
        'documentNumber' => 'PROD-'.str_pad((string) $produit->id, 6, '0', STR_PAD_LEFT),
        'documentDate' => 'Éditée le '.$generatedAt->format('d/m/Y à H:i'),
    ])

    <h1 class="product-name">{{ $produit->nom }}</h1>
    <div class="muted">{{ $produit->categorie ?: 'Sans catégorie' }}</div>

    <div class="section-title">Informations du produit</div>
    <table class="details">
        <tbody>
            <tr>
                <td>Code-barres</td>
                <td>{{ $produit->code_barre ?: 'Non renseigné' }}</td>
            </tr>
            <tr>
                <td>Prix d'achat</td>
                <td>{{ number_format($produit->prix_achat, 0, ',', ' ') }} {{ $currency }}</td>
            </tr>
            <tr>
                <td>Prix de vente</td>
                <td>{{ number_format($produit->prix_vente, 0, ',', ' ') }} {{ $currency }}</td>
            </tr>
            <tr>
                <td>Stock disponible</td>
                <td>{{ $produit->quantite_stock }}</td>
            </tr>
            <tr>
                <td>Seuil d'alerte</td>
                <td>{{ $produit->seuil_alerte }}</td>
            </tr>
            <tr>
                <td>État du catalogue</td>
                <td><span class="{{ $produit->actif ? 'status' : 'archived' }}">{{ $produit->actif ? 'Actif' : 'Archivé' }}</span></td>
            </tr>
            <tr>
                <td>Fiche créée le</td>
                <td>{{ $produit->created_at?->format('d/m/Y') ?? '—' }}</td>
            </tr>
        </tbody>
    </table>

    @if (filled($company['legal_notice'] ?? null))
        <div class="notice">{{ $company['legal_notice'] }}</div>
    @endif
</body>
</html>
