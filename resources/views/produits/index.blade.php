@extends('layouts.app')

@section('title', 'Stock produits - Boutik Plus')

@section('content')
    <div class="bp-page-heading">
        <div>
            <p class="bp-page-eyebrow">Catalogue et inventaire</p>
            <h1 class="h3">Produits en stock</h1>
            <p class="bp-page-description">Consultez les niveaux de stock et repérez rapidement les articles à réapprovisionner.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if (auth()->user()->estPatron())
                <a href="{{ route('produits.create') }}" class="btn btn-success"><i class="bi bi-plus-lg"></i> Ajouter un produit</a>
            @endif
        </div>
    </div>

    <form method="GET" action="{{ route('produits.index') }}" class="bp-filter-card">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-4">
                <label for="q" class="form-label">Recherche</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Nom, catégorie ou code-barres">
            </div>
            <div class="col-6 col-lg-2">
                <label for="statut" class="form-label">Catalogue</label>
                <select id="statut" name="statut" class="form-select">
                    <option value="actif" @selected(($filters['statut'] ?? 'actif') === 'actif')>Actifs</option>
                    <option value="archive" @selected(($filters['statut'] ?? '') === 'archive')>Archives</option>
                    <option value="tous" @selected(($filters['statut'] ?? '') === 'tous')>Tous</option>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="stock" class="form-label">Niveau de stock</label>
                <select id="stock" name="stock" class="form-select">
                    <option value="">Tous</option>
                    <option value="alerte" @selected(($filters['stock'] ?? '') === 'alerte')>À réapprovisionner</option>
                    <option value="disponible" @selected(($filters['stock'] ?? '') === 'disponible')>Au-dessus du seuil</option>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="date_debut" class="form-label">Créé du</label>
                <input id="date_debut" type="date" name="date_debut" value="{{ $filters['date_debut'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-lg-2">
                <label for="date_fin" class="form-label">Créé au</label>
                <input id="date_fin" type="date" name="date_fin" value="{{ $filters['date_fin'] ?? '' }}" class="form-control">
            </div>
            <div class="col-12 d-flex justify-content-end gap-2">
                <a href="{{ route('produits.index') }}" class="btn btn-outline-secondary">Effacer</a>
                <button class="btn btn-success" type="submit"><i class="bi bi-search"></i> Filtrer</button>
                <button class="btn btn-outline-danger" type="submit" name="pdf" value="1"><i class="bi bi-file-earmark-pdf"></i> Exporter PDF</button>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Catégorie</th>
                <th>Prix de vente</th>
                <th>Stock</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($produits as $produit)
                <tr>
                    <td>{{ $produit->nom }}</td>
                    <td>{{ $produit->categorie }}</td>
                    <td>{{ number_format($produit->prix_vente, 0, ',', ' ') }} FCFA</td>
                    <td>{{ $produit->quantite_stock }}</td>
                    <td>
                        @if (! $produit->actif)
                            <span class="badge bg-secondary">Archivé</span>
                        @elseif ($produit->estEnAlerte())
                            <span class="badge bg-danger">Stock faible</span>
                        @else
                            <span class="badge bg-success">OK</span>
                        @endif
                    </td>
                    <td>
                        @if (auth()->user()->estPatron())
                            <a href="{{ route('produits.pdf', $produit) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-pdf"></i> Fiche PDF</a>
                            @if ($produit->actif)
                                <a href="{{ route('produits.edit', $produit) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                                <form action="{{ route('produits.destroy', $produit) }}" method="POST" class="d-inline" data-confirm data-confirm-title="Archiver ce produit ?" data-confirm-message="« {{ $produit->nom }} » ne sera plus proposé à la vente, mais son historique de stock et de ventes sera conservé." data-confirm-label="Archiver" data-confirm-tone="danger">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i> Archiver</button>
                                </form>
                            @else
                                <form action="{{ route('produits.activer', $produit) }}" method="POST" class="d-inline" data-confirm data-confirm-title="Réactiver ce produit ?" data-confirm-message="« {{ $produit->nom }} » sera de nouveau disponible dans le catalogue." data-confirm-label="Réactiver">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-sm btn-outline-success"><i class="bi bi-arrow-counterclockwise"></i> Réactiver</button>
                                </form>
                                <form action="{{ route('produits.supprimer-definitivement', $produit) }}" method="POST" class="d-inline" data-confirm data-confirm-title="Supprimer définitivement ce produit ?" data-confirm-message="Cette suppression irréversible sera refusée si le produit a encore du stock ou possède un historique de stock, achat ou vente." data-confirm-label="Supprimer définitivement" data-confirm-tone="danger">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> Supprimer</button>
                                </form>
                            @endif
                        @else
                            <span class="text-muted">Consultation seule</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="bp-empty-cell"><i class="bi bi-box-seam"></i><strong>Aucun produit pour le moment</strong><span>Les produits apparaîtront ici dès leur création.</span></td></tr>
            @endforelse
        </tbody>
        </table>
    </div>
    {{ $produits->links() }}
@endsection
