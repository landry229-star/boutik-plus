<!DOCTYPE html>
<html lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Achat ACH-{{ str_pad((string) $achat->id, 6, '0', STR_PAD_LEFT) }}</title>
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
        .document-kind { color: #1b7a3d; font-size: 20px; font-weight: bold; text-align: right; }
        .document-number { color: #536159; text-align: right; }
        .section-title { color: #1b7a3d; font-size: 10px; font-weight: bold; margin: 0 0 6px; text-transform: uppercase; }
        .summary { margin: 0 0 20px; }
        .summary td { background: #f3f7f4; padding: 11px 12px; vertical-align: top; width: 50%; }
        .summary td + td { border-left: 5px solid #fff; }
        .muted { color: #65716a; }
        .status { background: #edf6ef; color: #1b7a3d; font-weight: bold; padding: 4px 8px; }
        .cancelled { background: #fff0f0; border: 1px solid #c23b3b; color: #a52222; font-weight: bold; margin: 12px 0; padding: 9px; }
        .items { margin-top: 10px; }
        .items thead { display: table-header-group; }
        .items th { background: #1b7a3d; color: #fff; font-size: 9px; padding: 9px 8px; text-align: left; }
        .items td { border-bottom: 1px solid #e1e7e3; padding: 9px 8px; }
        .items tbody tr:nth-child(even) { background: #f7f9f8; }
        .number { text-align: right !important; white-space: nowrap; }
        .totals { margin: 15px 0 0 auto; width: 48%; }
        .totals td { padding: 4px 6px; }
        .grand-total td { border-top: 2px solid #1b7a3d; color: #1b7a3d; font-size: 13px; font-weight: bold; padding-top: 9px; }
        .notes { background: #f7f9f8; margin-top: 18px; padding: 10px; }
        .payments { margin-top: 23px; }
        .payments th { background: #eef4f0; padding: 7px; text-align: left; }
        .payments td { border-bottom: 1px solid #e1e7e3; padding: 7px; }
        .notice { border-top: 1px solid #d9dfdb; color: #65716a; margin-top: 28px; padding-top: 10px; text-align: center; }
        .thank-you { color: #1b7a3d; font-weight: bold; }
    </style>
</head>
<body>
    @include('pdf.partials.company-header', [
        'documentTitle' => 'ACHAT FOURNISSEUR',
        'documentNumber' => 'ACH-'.str_pad((string) $achat->id, 6, '0', STR_PAD_LEFT),
        'documentDate' => 'Date : '.\Carbon\Carbon::parse($achat->date_achat)->format('d/m/Y'),
    ])

    @if ($achat->statut === 'annulee')
        <div class="cancelled">
            ACHAT ANNULÉ
            @if ($achat->annulee_at)
                le {{ $achat->annulee_at->format('d/m/Y à H:i') }}
            @endif
            @if ($achat->motif_annulation)
                <br>Motif : {{ $achat->motif_annulation }}
            @endif
            @if ($achat->annulee_par_nom_snapshot)
                <br>Annulé par : {{ $achat->annulee_par_nom_snapshot }}
            @endif
        </div>
    @endif

    <table class="summary">
        <tr>
            <td>
                <div class="section-title">Fournisseur</div>
                <strong>{{ $achat->fournisseur->nom }}</strong>
                @if ($achat->fournisseur->telephone)
                    <div class="muted">Téléphone : {{ $achat->fournisseur->telephone }}</div>
                @endif
                @if ($achat->fournisseur->adresse)
                    <div class="muted">Adresse : {{ $achat->fournisseur->adresse }}</div>
                @endif
            </td>
            <td>
                <div class="section-title">Suivi de l'achat</div>
                <div>Statut : <span class="status">{{ $achat->statut === 'annulee' ? 'Annulé' : 'Enregistré' }}</span></div>
                <div>Enregistré par : {{ $achat->utilisateur?->name ?? $achat->utilisateur_nom_snapshot ?? '—' }}</div>
                <div>Montant payé : {{ number_format($montantPaye, 0, ',', ' ') }} {{ $currency }}</div>
                <div>Reste dû : {{ number_format($montantDu, 0, ',', ' ') }} {{ $currency }}</div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Produit</th>
                <th class="number">Quantité</th>
                <th class="number">Prix d'achat unitaire</th>
                <th class="number">Sous-total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($achat->lignes as $ligne)
                <tr>
                    <td>{{ $ligne->produit?->nom ?? 'Produit indisponible' }}</td>
                    <td class="number">{{ $ligne->quantite }}</td>
                    <td class="number">{{ number_format($ligne->prix_achat_unitaire, 0, ',', ' ') }} {{ $currency }}</td>
                    <td class="number">{{ number_format($ligne->quantite * $ligne->prix_achat_unitaire, 0, ',', ' ') }} {{ $currency }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr class="grand-total">
            <td>Total de l'achat</td>
            <td class="number">{{ number_format($achat->montant_total, 0, ',', ' ') }} {{ $currency }}</td>
        </tr>
    </table>

    @if ($paiements->isNotEmpty())
        <table class="payments">
            <thead>
                <tr>
                    <th colspan="3">Paiements enregistrés</th>
                </tr>
                <tr>
                    <th>Date</th>
                    <th>Moyen de paiement</th>
                    <th class="number">Montant</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($paiements as $paiement)
                    <tr>
                        <td>{{ $paiement->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $paiement->moyen_paiement === 'mobile_money' ? 'Mobile Money' : 'Espèces' }}</td>
                        <td class="number">{{ number_format($paiement->montant, 0, ',', ' ') }} {{ $currency }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($achat->notes)
        <div class="notes"><strong>Notes :</strong> {{ $achat->notes }}</div>
    @endif

    @if (filled($company['legal_notice'] ?? null))
        <div class="notice">{{ $company['legal_notice'] }}</div>
    @endif
</body>
</html>
