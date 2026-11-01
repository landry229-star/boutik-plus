@extends('layouts.app')

@section('title', 'Fournisseurs - Boutik Plus')

@section('content')
    <div class="bp-page-heading">
        <div>
            <p class="bp-page-eyebrow">Partenaires commerciaux</p>
            <h1 class="h3">Fournisseurs</h1>
            <p class="bp-page-description">Gérez les contacts fournisseurs et conservez leur historique d'achats en cas d'archivage.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('fournisseurs.create') }}" class="btn btn-success"><i class="bi bi-plus-lg"></i> Ajouter un fournisseur</a>
        </div>
    </div>

    <form method="GET" action="{{ route('fournisseurs.index') }}" class="bp-filter-card">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-4">
                <label for="q" class="form-label">Recherche</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Nom, téléphone ou adresse">
            </div>
            <div class="col-6 col-lg-2">
                <label for="statut" class="form-label">Statut</label>
                <select id="statut" name="statut" class="form-select">
                    <option value="actif" @selected(($filters['statut'] ?? 'actif') === 'actif')>Actifs</option>
                    <option value="archive" @selected(($filters['statut'] ?? '') === 'archive')>Archives</option>
                    <option value="tous" @selected(($filters['statut'] ?? '') === 'tous')>Tous</option>
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <label for="date_debut" class="form-label">Créé du</label>
                <input id="date_debut" type="date" name="date_debut" value="{{ $filters['date_debut'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-lg-3">
                <label for="date_fin" class="form-label">Créé au</label>
                <input id="date_fin" type="date" name="date_fin" value="{{ $filters['date_fin'] ?? '' }}" class="form-control">
            </div>
            <div class="col-12 d-flex justify-content-end gap-2">
                <a href="{{ route('fournisseurs.index') }}" class="btn btn-outline-secondary">Effacer</a>
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
                <th>Téléphone</th>
                <th>Adresse</th>
                <th>Achats effectués</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($fournisseurs as $fournisseur)
                <tr>
                    <td>{{ $fournisseur->nom }}</td>
                    <td>{{ $fournisseur->telephone }}</td>
                    <td>{{ $fournisseur->adresse }}</td>
                    <td>{{ $fournisseur->achats_count }}</td>
                    <td><span class="badge {{ $fournisseur->actif ? 'bp-badge-success' : 'text-bg-secondary' }}">{{ $fournisseur->actif ? 'Actif' : 'Archivé' }}</span></td>
                    <td>
                        @if ($fournisseur->actif)
                            <a href="{{ route('fournisseurs.edit', $fournisseur) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                            <form action="{{ route('fournisseurs.destroy', $fournisseur) }}" method="POST" class="d-inline" data-confirm data-confirm-title="Archiver ce fournisseur ?" data-confirm-message="« {{ $fournisseur->nom }} » ne sera plus disponible pour les nouveaux achats. Son historique d'achats sera conservé." data-confirm-label="Archiver" data-confirm-tone="danger">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-archive"></i> Archiver</button>
                            </form>
                        @else
                            <form action="{{ route('fournisseurs.activer', $fournisseur) }}" method="POST" class="d-inline" data-confirm data-confirm-title="Réactiver ce fournisseur ?" data-confirm-message="« {{ $fournisseur->nom }} » sera proposé à nouveau lors de la création d'un achat." data-confirm-label="Réactiver">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-sm btn-outline-success"><i class="bi bi-arrow-counterclockwise"></i> Réactiver</button>
                            </form>
                            @if ($fournisseur->achats_count === 0)
                                <form action="{{ route('fournisseurs.supprimer-definitivement', $fournisseur) }}" method="POST" class="d-inline" data-confirm data-confirm-title="Supprimer définitivement ce fournisseur ?" data-confirm-message="La suppression est irréversible et sera refusée si un achat est lié à ce fournisseur." data-confirm-label="Supprimer définitivement" data-confirm-tone="danger">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> Supprimer</button>
                                </form>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="bp-empty-cell"><i class="bi bi-truck"></i><strong>Aucun fournisseur enregistré</strong><span>Ajoutez un fournisseur pour commencer à saisir vos achats.</span></td></tr>
            @endforelse
        </tbody>
        </table>
    </div>
    {{ $fournisseurs->links() }}
@endsection
