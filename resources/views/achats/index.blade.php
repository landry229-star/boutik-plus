@extends('layouts.app')

@section('title', 'Achats fournisseurs - Boutik Plus')

@section('content')
    <div class="bp-page-heading">
        <div>
            <p class="bp-page-eyebrow">Approvisionnement et règlements</p>
            <h1 class="h3">Achats fournisseurs</h1>
            <p class="bp-page-description">Suivez les achats enregistrés et accédez au détail de chaque paiement fournisseur.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('achats.create') }}" class="btn btn-success"><i class="bi bi-plus-lg"></i> Nouvel achat</a>
        </div>
    </div>

    <form method="GET" action="{{ route('achats.index') }}" class="bp-filter-card">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-3">
                <label for="q" class="form-label">Recherche fournisseur</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Nom du fournisseur">
            </div>
            <div class="col-12 col-lg-3">
                <label for="fournisseur_id" class="form-label">Fournisseur</label>
                <select id="fournisseur_id" name="fournisseur_id" class="form-select">
                    <option value="">Tous</option>
                    @foreach ($fournisseursFiltres as $fournisseurFiltre)
                        <option value="{{ $fournisseurFiltre->id }}" @selected((string) ($filters['fournisseur_id'] ?? '') === (string) $fournisseurFiltre->id)>{{ $fournisseurFiltre->nom }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="statut" class="form-label">Statut</label>
                <select id="statut" name="statut" class="form-select">
                    <option value="tous" @selected(($filters['statut'] ?? 'tous') === 'tous')>Tous</option>
                    <option value="enregistree" @selected(($filters['statut'] ?? '') === 'enregistree')>Enregistrés</option>
                    <option value="annulee" @selected(($filters['statut'] ?? '') === 'annulee')>Annulés</option>
                </select>
            </div>
            <div class="col-6 col-lg-1">
                <label for="date_debut" class="form-label">Du</label>
                <input id="date_debut" type="date" name="date_debut" value="{{ $filters['date_debut'] ?? '' }}" class="form-control">
            </div>
            <div class="col-6 col-lg-1">
                <label for="date_fin" class="form-label">Au</label>
                <input id="date_fin" type="date" name="date_fin" value="{{ $filters['date_fin'] ?? '' }}" class="form-control">
            </div>
            <div class="col-12 col-lg-2 d-flex gap-2">
                <a href="{{ route('achats.index') }}" class="btn btn-outline-secondary">Effacer</a>
                <button class="btn btn-success flex-grow-1" type="submit"><i class="bi bi-search"></i> Filtrer</button>
                <button class="btn btn-outline-danger" type="submit" name="pdf" value="1"><i class="bi bi-file-earmark-pdf"></i></button>
            </div>
        </div>
    </form>
    <p class="bp-form-hint mb-3"><i class="bi bi-info-circle"></i> Un achat enregistré ne se modifie pas : annulez-le si aucun paiement n’a été fait et que le stock le permet, puis recréez-le correctement.</p>

    <div class="table-responsive">
        <table class="table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Fournisseur</th>
                <th>Montant total</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($achats as $achat)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($achat->date_achat)->format('d/m/Y') }}</td>
                    <td>{{ $achat->fournisseur->nom }}</td>
                    <td>{{ number_format($achat->montant_total, 0, ',', ' ') }} FCFA</td>
                    <td><span class="badge {{ $achat->statut === 'annulee' ? 'text-bg-secondary' : 'bp-badge-success' }}">{{ $achat->statut === 'annulee' ? 'Annulé' : 'Enregistré' }}</span></td>
                    <td>
                        <a href="{{ route('achats.show', $achat) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Détails</a>
                        @if ($achat->statut !== 'annulee' && ($achat->montant_paye ?? 0) <= 0)
                            <a href="{{ route('achats.show', $achat) }}#annulation-achat" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i> Annuler</a>
                        @endif
                        @if ($achat->statut === 'annulee')
                            <form action="{{ route('achats.supprimer-definitivement', $achat) }}" method="POST" class="d-inline" data-confirm data-confirm-title="Supprimer définitivement cet achat ?" data-confirm-message="L’achat annulé, ses lignes et ses mouvements de stock liés seront effacés. Cette action est irréversible." data-confirm-label="Supprimer définitivement" data-confirm-tone="danger">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> Supprimer</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="bp-empty-cell"><i class="bi bi-bag"></i><strong>Aucun achat fournisseur</strong><span>Les approvisionnements enregistrés apparaîtront ici.</span></td></tr>
            @endforelse
        </tbody>
        </table>
    </div>
    {{ $achats->links() }}
@endsection
