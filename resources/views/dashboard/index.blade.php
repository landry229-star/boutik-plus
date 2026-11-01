@extends('layouts.app')

@section('title', 'Tableau de bord - Boutik Plus')

@section('content')
    <div class="bp-dashboard-heading">
        <div>
            <p class="bp-dashboard-eyebrow">Vue d’ensemble</p>
            <h1 class="h3"><i class="bi bi-grid-1x2"></i> Tableau de bord</h1>
        </div>
        <span class="bp-dashboard-date"><i class="bi bi-calendar3"></i> {{ \Carbon\Carbon::now()->format('d/m/Y') }}</span>
    </div>

    <form method="GET" action="{{ route('dashboard.index') }}" class="row g-3 align-items-end bp-filter-card">
        <div class="col-sm-4 col-md-3">
            <label for="date_debut" class="form-label">Début de période</label>
            <input id="date_debut" type="date" name="date_debut" value="{{ $dateDebut }}" class="form-control" required>
        </div>
        <div class="col-sm-4 col-md-3">
            <label for="date_fin" class="form-label">Fin de période</label>
            <input id="date_fin" type="date" name="date_fin" value="{{ $dateFin }}" class="form-control" required>
        </div>
        <div class="col-sm-4 col-md-3">
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-outline-success">Afficher le rapport</button>
                <button type="submit" name="pdf" value="1" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> Exporter les tableaux PDF</button>
            </div>
        </div>
    </form>

    <div class="row mb-4 gy-3">
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card bp-stat-card bp-stat-day">
                <div class="card-body">
                    <div class="bp-stat-heading"><span>Ventes aujourd’hui</span><i class="bi bi-cash-coin"></i></div>
                    <div class="bp-stat-value">{{ number_format($ventesJour, 0, ',', ' ') }} <span>FCFA</span></div>
                    <div class="bp-stat-caption">{{ $nombreFacturesJour }} facture(s) enregistrée(s)</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card bp-stat-card bp-stat-month">
                <div class="card-body">
                    <div class="bp-stat-heading"><span>Ventes ce mois</span><i class="bi bi-graph-up-arrow"></i></div>
                    <div class="bp-stat-value">{{ number_format($ventesMois, 0, ',', ' ') }} <span>FCFA</span></div>
                    <div class="bp-stat-caption">Total des ventes du mois en cours</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card bp-stat-card bp-stat-stock">
                <div class="card-body">
                    <div class="bp-stat-heading"><span>Alertes de stock</span><i class="bi bi-exclamation-triangle"></i></div>
                    <div class="bp-stat-value">{{ $produitsEnAlerte->count() }} <span>produit(s)</span></div>
                    <div class="bp-stat-caption">À vérifier pour anticiper le réapprovisionnement</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-5">
        <div class="card-header bp-report-header">
            <span><i class="bi bi-calendar-range"></i> Rapport de période</span>
            <span>{{ \Carbon\Carbon::parse($dateDebut)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($dateFin)->format('d/m/Y') }}</span>
            <a href="{{ route('dashboard.index', ['date_debut' => $dateDebut, 'date_fin' => $dateFin, 'table' => 'paiements', 'pdf' => 1]) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> PDF paiements</a>
        </div>
        <div class="card-body">
            <p class="bp-report-total"><strong>{{ number_format($montantPeriode, 0, ',', ' ') }} FCFA</strong><span>de ventes sur {{ $nombreFacturesPeriode }} facture(s) payée(s)</span></p>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Moyen de paiement</th><th>Montant</th></tr></thead>
                    <tbody>
                        <tr><td>Espèces</td><td>{{ number_format($ventesParPaiement->get('especes')?->montant ?? 0, 0, ',', ' ') }} FCFA</td></tr>
                        <tr><td>Mobile Money</td><td>{{ number_format($ventesParPaiement->get('mobile_money')?->montant ?? 0, 0, ',', ' ') }} FCFA</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row mb-5 gy-4">
        <div class="col-md-7">
            <div class="card p-3">
                <h2 class="h6">Evolution des ventes (7 derniers jours)</h2>
                <canvas id="graphiqueVentes" height="140"></canvas>
            </div>
        </div>
        <div class="col-md-5">
            <div class="card p-3">
                <h2 class="h6">Repartition du top 5 produits (par quantite)</h2>
                <canvas id="graphiqueTopProduits" height="140"></canvas>
            </div>
        </div>
    </div>

    <div class="row gy-4">
        <div class="col-md-6">
            <h2 class="h5 d-flex align-items-center justify-content-between gap-2">
                Top 5 des produits sur la période choisie
                <a href="{{ route('dashboard.index', ['date_debut' => $dateDebut, 'date_fin' => $dateFin, 'table' => 'top_produits', 'pdf' => 1]) }}" class="btn btn-sm btn-outline-danger text-nowrap"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
            </h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Quantité vendue</th>
                            <th>Montant généré</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topProduits as $ligne)
                            <tr>
                                <td>{{ $ligne->produit->nom ?? 'Produit supprimé' }}</td>
                                <td>{{ $ligne->total_quantite }}</td>
                                <td>{{ number_format($ligne->total_montant, 0, ',', ' ') }} FCFA</td>
                                <td>
                                    @if ($ligne->produit)
                                        <a href="{{ route('produits.edit', $ligne->produit) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-seam"></i> Gérer le produit</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4">Aucune vente sur la période sélectionnée.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-md-6">
            <h2 class="h5 d-flex align-items-center justify-content-between gap-2">
                Produits à réapprovisionner
                <a href="{{ route('dashboard.index', ['date_debut' => $dateDebut, 'date_fin' => $dateFin, 'table' => 'alertes_stock', 'pdf' => 1]) }}" class="btn btn-sm btn-outline-danger text-nowrap"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
            </h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Stock restant</th>
                            <th>Seuil d’alerte</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($produitsEnAlerte as $produit)
                            <tr>
                                <td>{{ $produit->nom }}</td>
                                <td>{{ $produit->quantite_stock }}</td>
                                <td>{{ $produit->seuil_alerte }}</td>
                                <td><a href="{{ route('produits.edit', $produit) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Gérer le stock</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4">Aucun produit en alerte, tout va bien.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        const ctxVentes = document.getElementById('graphiqueVentes');
        new Chart(ctxVentes, {
            type: 'line',
            data: {
                labels: @json(collect($ventes7Jours)->pluck('jour')),
                datasets: [{
                    label: 'Ventes (FCFA)',
                    data: @json(collect($ventes7Jours)->pluck('montant')),
                    borderColor: '#1b7a3d',
                    backgroundColor: 'rgba(27, 122, 61, 0.15)',
                    fill: true,
                    tension: 0.3,
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });

        const ctxTop = document.getElementById('graphiqueTopProduits');
        new Chart(ctxTop, {
            type: 'doughnut',
            data: {
                labels: @json($topProduits->map(fn($l) => $l->produit->nom ?? 'Produit supprime')),
                datasets: [{
                    data: @json($topProduits->pluck('total_quantite')),
                    backgroundColor: ['#1b7a3d', '#e8823a', '#2f9e56', '#f0a868', '#145c2e'],
                }]
            },
            options: {
                plugins: { legend: { position: 'bottom' } }
            }
        });
    </script>
@endsection
