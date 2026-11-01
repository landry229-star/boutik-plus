<!DOCTYPE html>
<html lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Facture {{ $facture->numero }}</title>
    <style>
        @page { margin: 34px 38px; }
        body { color: #26332b; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.5; }
        table { border-collapse: collapse; width: 100%; }
        .header { border-bottom: 2px solid #1b7a3d; margin-bottom: 24px; padding-bottom: 16px; }
        .header td { vertical-align: top; }
        .logo-cell { padding-right: 14px; width: 112px; }
        .logo { max-height: 70px; max-width: 105px; }
        .company-name { color: #1b7a3d; font-size: 19px; font-weight: bold; margin-bottom: 4px; }
        .company-details { color: #536159; }
        .document-kind { color: #1b7a3d; font-size: 22px; font-weight: bold; text-align: right; }
        .document-number { color: #536159; text-align: right; }
        .section-title { color: #1b7a3d; font-size: 10px; font-weight: bold; margin: 0 0 6px; text-transform: uppercase; }
        .summary { margin: 0 0 22px; }
        .summary td { background: #f3f7f4; padding: 11px 12px; vertical-align: top; width: 50%; }
        .summary td + td { border-left: 5px solid #fff; }
        .muted { color: #65716a; }
        .cancelled { background: #fff0f0; border: 1px solid #c23b3b; color: #a52222; font-weight: bold; margin: 12px 0; padding: 9px; }
        .pending { background: #fff8e6; border: 1px solid #d9a441; color: #805b08; font-weight: bold; margin: 12px 0; padding: 9px; }
        .items { margin-top: 10px; }
        .items thead { display: table-header-group; }
        .items th { background: #1b7a3d; color: #fff; font-size: 9px; padding: 9px 8px; text-align: left; }
        .items td { border-bottom: 1px solid #e1e7e3; padding: 9px 8px; }
        .items tbody tr:nth-child(even) { background: #f7f9f8; }
        .number { text-align: right !important; white-space: nowrap; }
        .totals { margin: 15px 0 0 auto; width: 48%; }
        .totals td { padding: 4px 6px; }
        .grand-total td { border-top: 2px solid #1b7a3d; color: #1b7a3d; font-size: 13px; font-weight: bold; padding-top: 9px; }
        .notice { border-top: 1px solid #d9dfdb; color: #65716a; margin-top: 28px; padding-top: 10px; text-align: center; }
        .thank-you { color: #1b7a3d; font-weight: bold; }
    </style>
</head>
<body>
    @include('pdf.partials.company-header', [
        'documentTitle' => 'FACTURE',
        'documentNumber' => $facture->numero,
        'documentDate' => 'Émise le '.$facture->created_at->format('d/m/Y à H:i'),
    ])

    @if ($facture->statut === 'annulee')
        <div class="cancelled">
            FACTURE ANNULÉE
            @if ($facture->annulee_at)
                le {{ $facture->annulee_at->format('d/m/Y à H:i') }}
            @endif
            @if ($facture->statut === 'en_attente')
                <div class="pending">PAIEMENT EN ATTENTE — cette facture n’est pas encore validée.</div>
            @endif
            @if ($format === 'complet' && $facture->motif_annulation)
                <br>Motif : {{ $facture->motif_annulation }}
            @endif
        </div>
    @endif

    <table class="summary">
        <tr>
            <td>
                <div class="section-title">Facturé à</div>
                <strong>{{ $facture->client_nom ?: 'Client comptant' }}</strong>
                @if ($format === 'complet' && $facture->client_telephone)
                    <div class="muted">Téléphone : {{ $facture->client_telephone }}</div>
                @endif
                @if ($format === 'complet' && $facture->client_email)
                    <div class="muted">E-mail : {{ $facture->client_email }}</div>
                @endif
            </td>
            <td>
                <div class="section-title">Informations de vente</div>
                <div><strong>Date :</strong> {{ $facture->created_at->format('d/m/Y à H:i') }}</div>
                <div><strong>Paiement :</strong> {{ $facture->mode_paiement === 'mobile_money' ? 'Mobile Money' : 'Espèces' }}</div>
                @if ($format === 'complet')
                    <div><strong>Enregistrée par :</strong> {{ $facture->utilisateur?->name ?? $facture->utilisateur_nom_snapshot ?? '—' }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Article</th>
                <th class="number">Quantité</th>
                <th class="number">Prix unitaire</th>
                <th class="number">Montant</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($facture->lignes as $ligne)
                <tr>
                    <td>{{ $ligne->produit?->nom ?? 'Article indisponible' }}</td>
                    <td class="number">{{ $ligne->quantite }}</td>
                    <td class="number">{{ number_format($ligne->prix_unitaire, 0, ',', ' ') }} {{ $currency }}</td>
                    <td class="number">{{ number_format($ligne->sous_total, 0, ',', ' ') }} {{ $currency }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        @if ($facture->montant_taxe > 0)
            <tr>
                <td>Montant hors taxe</td>
                <td class="number">{{ number_format($facture->montant_ht, 0, ',', ' ') }} {{ $currency }}</td>
            </tr>
            <tr>
                <td>{{ $facture->libelle_taxe ?: 'Taxe' }} ({{ number_format($facture->taux_taxe, 2, ',', ' ') }} %)</td>
                <td class="number">{{ number_format($facture->montant_taxe, 0, ',', ' ') }} {{ $currency }}</td>
            </tr>
        @endif
        <tr class="grand-total">
            <td>Total à payer</td>
            <td class="number">{{ number_format($facture->montant_total, 0, ',', ' ') }} {{ $currency }}</td>
        </tr>
    </table>

    <div class="notice">
        <div class="thank-you">Merci pour votre confiance et votre achat.</div>
        @if (filled($company['legal_notice'] ?? null))
            <div>{{ $company['legal_notice'] }}</div>
        @endif
    </div>
</body>
</html>
