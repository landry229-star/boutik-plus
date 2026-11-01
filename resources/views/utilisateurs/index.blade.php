@extends('layouts.app')

@section('title', 'Équipe - Boutik Plus')

@section('content')
    <div class="bp-page-heading">
        <div>
            <p class="bp-page-eyebrow">Accès et permissions</p>
            <h1 class="h3">Équipe</h1>
            <p class="bp-page-description">Gérez les accès employés. La désactivation bloque la connexion mais conserve l'historique associé.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('utilisateurs.create') }}" class="btn btn-success"><i class="bi bi-person-plus"></i> Ajouter un employé</a>
        </div>
    </div>

    <form method="GET" action="{{ route('utilisateurs.index') }}" class="bp-filter-card">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-4">
                <label for="q" class="form-label">Recherche</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Nom ou adresse e-mail">
            </div>
            <div class="col-6 col-lg-2">
                <label for="statut" class="form-label">Accès</label>
                <select id="statut" name="statut" class="form-select">
                    <option value="actif" @selected(($filters['statut'] ?? 'actif') === 'actif')>Actifs</option>
                    <option value="archive" @selected(($filters['statut'] ?? '') === 'archive')>Désactivés</option>
                    <option value="tous" @selected(($filters['statut'] ?? '') === 'tous')>Tous</option>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="role" class="form-label">Rôle</label>
                <select id="role" name="role" class="form-select">
                    <option value="tous" @selected(($filters['role'] ?? 'tous') === 'tous')>Tous</option>
                    <option value="patron" @selected(($filters['role'] ?? '') === 'patron')>Patron</option>
                    <option value="employe" @selected(($filters['role'] ?? '') === 'employe')>Employé</option>
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
                <a href="{{ route('utilisateurs.index') }}" class="btn btn-outline-secondary">Effacer</a>
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
                <th>Email</th>
                <th>Role</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($utilisateurs as $utilisateur)
                <tr>
                    <td>{{ $utilisateur->name }}</td>
                    <td>{{ $utilisateur->email }}</td>
                    <td><span class="badge {{ $utilisateur->estPatron() ? 'bp-badge-role' : 'text-bg-light' }}">{{ $utilisateur->estPatron() ? 'Patron' : 'Employé' }}</span></td>
                    <td><span class="badge {{ $utilisateur->actif ? 'bp-badge-success' : 'text-bg-secondary' }}">{{ $utilisateur->actif ? 'Actif' : 'Désactivé' }}</span></td>
                    <td>
                        @if ($utilisateur->id !== auth()->id() && ! $utilisateur->estPatron())
                            <a href="{{ route('utilisateurs.edit', $utilisateur) }}" class="btn btn-sm btn-outline-primary">Modifier</a>
                            <form action="{{ route('utilisateurs.statut', $utilisateur) }}" method="POST" class="d-inline" data-confirm data-confirm-title="{{ $utilisateur->actif ? 'Désactiver cet accès ?' : 'Réactiver cet accès ?' }}" data-confirm-message="{{ $utilisateur->actif ? 'L’employé '.$utilisateur->name.' ne pourra plus se connecter. Son historique restera conservé.' : 'L’employé '.$utilisateur->name.' pourra de nouveau se connecter avec ses identifiants actuels.' }}" data-confirm-label="{{ $utilisateur->actif ? 'Désactiver' : 'Réactiver' }}" @if ($utilisateur->actif) data-confirm-tone="danger" @endif>
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="actif" value="{{ $utilisateur->actif ? 0 : 1 }}">
                                <button class="btn btn-sm {{ $utilisateur->actif ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                    {{ $utilisateur->actif ? 'Désactiver' : 'Réactiver' }}
                                </button>
                            </form>
                            @if (! $utilisateur->actif && ! $utilisateur->factures_exists && ! $utilisateur->factures_annulees_exists && ! $utilisateur->achats_exists && ! $utilisateur->mouvements_stock_exists && ! $utilisateur->mouvements_caisse_exists)
                                <form action="{{ route('utilisateurs.supprimer-definitivement', $utilisateur) }}" method="POST" class="d-inline" data-confirm data-confirm-title="Supprimer définitivement ce compte ?" data-confirm-message="Cette suppression est irréversible et sera refusée si le compte est lié à des ventes, achats, mouvements de caisse ou de stock." data-confirm-label="Supprimer définitivement" data-confirm-tone="danger">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> Supprimer</button>
                                </form>
                            @endif
                        @elseif ($utilisateur->id === auth()->id())
                            <span class="text-muted">Compte connecté</span>
                        @else
                            <span class="text-muted">Compte patron protégé</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="bp-empty-cell"><i class="bi bi-people"></i><strong>Aucun compte d'équipe</strong><span>Les comptes patron et employés apparaîtront ici.</span></td></tr>
            @endforelse
        </tbody>
        </table>
    </div>
    {{ $utilisateurs->links() }}
@endsection
