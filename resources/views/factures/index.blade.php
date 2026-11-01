@extends('layouts.app')

@section('title', 'Factures - Boutik Plus')

@section('content')
    <div class="bp-page-heading">
        <div>
            <p class="bp-page-eyebrow">Ventes et encaissements</p>
            <h1 class="h3">Factures</h1>
            <p class="bp-page-description">Retrouvez les ventes, consultez leurs détails et accédez aux reçus PDF.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('factures.create') }}" class="btn btn-success"><i class="bi bi-plus-lg"></i> Nouvelle facture</a>
        </div>
    </div>

    <form method="GET" action="{{ route('factures.index') }}" class="bp-filter-card">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-3">
                <label for="q" class="form-label">Recherche</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Numéro ou client">
            </div>
            <div class="col-6 col-lg-2">
                <label for="statut" class="form-label">Statut</label>
                <select id="statut" name="statut" class="form-select">
                    <option value="tous" @selected(($filters['statut'] ?? 'tous') === 'tous')>Tous</option>
                    <option value="payee" @selected(($filters['statut'] ?? '') === 'payee')>Payées</option>
                    <option value="en_attente" @selected(($filters['statut'] ?? '') === 'en_attente')>Paiements en attente</option>
                    <option value="annulee" @selected(($filters['statut'] ?? '') === 'annulee')>Annulées</option>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="moyen_paiement" class="form-label">Paiement</label>
                <select id="moyen_paiement" name="moyen_paiement" class="form-select">
                    <option value="">Tous</option>
                    <option value="especes" @selected(($filters['moyen_paiement'] ?? '') === 'especes')>Espèces</option>
                    <option value="mobile_money" @selected(($filters['moyen_paiement'] ?? '') === 'mobile_money')>Mobile Money</option>
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
            <div class="col-12 d-flex justify-content-end gap-2">
                <a href="{{ route('factures.index') }}" class="btn btn-outline-secondary">Effacer</a>
                <button class="btn btn-success flex-grow-1" type="submit"><i class="bi bi-search"></i> Filtrer</button>
                <button class="btn btn-outline-danger" type="submit" name="pdf" value="1"><i class="bi bi-file-earmark-pdf"></i> Exporter PDF</button>
            </div>
        </div>
    </form>
    <p class="bp-form-hint mb-3"><i class="bi bi-info-circle"></i> Une facture émise reste dans l’historique : pour la corriger, ouvrez son détail, annulez-la avec un motif puis créez une nouvelle facture.</p>

    <div class="table-responsive">
        <table class="table">
        <thead>
            <tr>
                <th>Numéro</th>
                <th>Client</th>
                <th>Montant total</th>
                <th>Paiement</th>
                <th>Statut</th>
                <th>Vendeur</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($factures as $facture)
                <tr>
                    <td>{{ $facture->numero }}</td>
                    <td>{{ $facture->client_nom ?? 'Client comptant' }}</td>
                    <td>{{ number_format($facture->montant_total, 0, ',', ' ') }} FCFA</td>
                    <td>{{ $facture->mode_paiement === 'mobile_money' ? 'Mobile Money' : 'Espèces' }}</td>
                    <td>
                        <span @class([
                            'badge',
                            'text-bg-secondary' => $facture->statut === 'annulee',
                            'text-bg-warning' => $facture->statut === 'en_attente',
                            'bp-badge-success' => $facture->statut === 'payee',
                        ])>
                            {{ $facture->statut === 'annulee' ? 'Annulée' : ($facture->statut === 'en_attente' ? 'Paiement en attente' : 'Payée') }}
                        </span>
                    </td>
                    <td>{{ $facture->utilisateur->name ?? $facture->utilisateur_nom_snapshot ?? '—' }}</td>
                    <td>{{ $facture->created_at->format('d/m/Y H:i') }}</td>
                    <td class="text-nowrap">
                        <a href="{{ route('factures.show', $facture) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Détails</a>
                        @if ($facture->statut === 'payee'
                            && auth()->user()->estPatron()
                            && ! $facture->paiementsMobileMoney->contains(fn ($paiement) => $paiement->provider === 'fedapay' && $paiement->status === 'approved'))
                            <a href="{{ route('factures.show', $facture) }}#motif_annulation" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i> Annuler</a>
                        @endif
                        @if ($facture->statut === 'annulee' && auth()->user()->estPatron())
                            <form action="{{ route('factures.supprimer-definitivement', $facture) }}" method="POST" class="d-inline" data-confirm data-confirm-title="Supprimer définitivement cette facture ?" data-confirm-message="La facture annulée, ses lignes, ses paiements Mobile Money et ses mouvements de caisse et de stock liés seront effacés. Cette action est irréversible." data-confirm-label="Supprimer définitivement" data-confirm-tone="danger">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> Supprimer</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="bp-empty-cell"><i class="bi bi-receipt"></i><strong>Aucune facture enregistrée</strong><span>Créez une facture après avoir sélectionné les produits vendus.</span></td></tr>
            @endforelse
        </tbody>
        </table>
    </div>
    {{ $factures->links() }}
@endsection
