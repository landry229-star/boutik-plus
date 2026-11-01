<!DOCTYPE html>
<html lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { color: #26332b; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.45; }
        table { border-collapse: collapse; width: 100%; }
        .header { border-bottom: 2px solid #1b7a3d; margin-bottom: 16px; padding-bottom: 12px; }
        .header td { vertical-align: top; }
        .logo-cell { padding-right: 12px; width: 95px; }
        .logo { max-height: 58px; max-width: 90px; }
        .company-name { color: #1b7a3d; font-size: 17px; font-weight: bold; margin-bottom: 3px; }
        .company-details, .muted { color: #65716a; }
        .document-kind { color: #1b7a3d; font-size: 16px; font-weight: bold; text-align: right; }
        .document-number { color: #65716a; text-align: right; }
        .criteria { background: #f3f7f4; margin: 0 0 16px; padding: 8px; }
        .section { margin: 0 0 18px; }
        .section-title { color: #1b7a3d; font-size: 11px; font-weight: bold; margin: 0 0 6px; }
        .data-page + .data-page { page-break-before: always; }
        .data-table { border-collapse: collapse; page-break-inside: avoid; table-layout: fixed; width: 100%; }
        .data-table th { background: #1b7a3d; color: #fff; font-size: 7px; font-weight: bold; padding: 4px; text-align: left; }
        .data-table td { border-bottom: 1px solid #d9dfdb; font-size: 7px; padding: 3px 4px; vertical-align: top; word-wrap: break-word; }
        .data-table tbody tr:nth-child(even) { background: #f3f7f4; }
        .empty { color: #65716a; padding: 10px 6px; }
        .footer { border-top: 1px solid #d9dfdb; color: #65716a; margin-top: 18px; padding-top: 7px; text-align: center; }
    </style>
</head>
<body>
    @include('pdf.partials.company-header', [
        'documentTitle' => $title,
        'documentNumber' => '',
        'documentDate' => 'Généré le '.$generatedAt->format('d/m/Y à H:i'),
    ])

    @if ($criteria !== [])
        <div class="criteria"><strong>Filtres appliqués :</strong> {{ implode(' · ', $criteria) }}</div>
    @endif

    @foreach ($sections as $section)
        <section class="section">
            <h2 class="section-title">{{ $section['title'] }}</h2>
            @forelse (array_chunk($section['rows'], 35) as $pageIndex => $pageRows)
                <div class="data-page" @if ($pageIndex > 0) style="page-break-before: always;" @endif>
                    <table class="data-table">
                        <thead>
                            <tr>
                                @foreach ($section['headers'] as $header)
                                    <th>{{ $header }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pageRows as $row)
                                <tr>
                                    @foreach ($row as $cell)
                                        <td>{{ $cell }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @empty
                <table class="data-table">
                    <thead>
                        <tr>
                            @foreach ($section['headers'] as $header)
                                <th>{{ $header }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody><tr><td colspan="{{ count($section['headers']) }}" class="empty">Aucune donnée pour les critères sélectionnés.</td></tr></tbody>
                </table>
            @endforelse
        </section>
    @endforeach

    <div class="footer">{{ $company['name'] }} · Document généré depuis Boutik Plus</div>
</body>
</html>
