@extends('layouts.app')

@section('title', 'Détail d’achat - Boutik Plus')

@section('content')
    <div class="bp-page-heading">
        <div>
            <p class="bp-page-eyebrow">Approvisionnement et règlements</p>
            <h1 class="h3">Achat du {{ \Carbon\Carbon::parse($achat->date_achat)->format('d/m/Y') }}</h1>
            <p class="bp-page-description">Fournisseur : {{ $achat->fournisseur->nom }} <span class="bp-inline-separator">·</span> Enregistré par {{ $achat->utilisateur->name ?? $achat->utilisateur_nom_snapshot ?? '—' }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('achats.pdf', $achat) }}" class="btn btn-outline-primary"><i class="bi bi-file-earmark-pdf"></i> Télécharger le PDF</a>
            <a href="{{ route('achats.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour aux achats</a>
        </div>
    </div>

    <div class="bp-detail-meta">
        <span class="badge {{ $achat->statut === 'annulee' ? 'text-bg-secondary' : 'bp-badge-success' }}">{{ $achat->statut === 'annulee' ? 'Annulé' : 'Enregistré' }}</span>
        @if ($achat->annulee_at)
            <span>Annulé le {{ $achat->annulee_at->format('d/m/Y H:i') }} · Motif : {{ $achat->motif_annulation }}</span>
        @endif
    </div>

    <div class="bp-purchase-summary mb-4">
        <div><span>Total de l'achat</span><strong>{{ number_format($achat->montant_total, 0, ',', ' ') }} FCFA</strong></div>
        <div><span>Déjà payé</span><strong>{{ number_format($montantPaye, 0, ',', ' ') }} FCFA</strong></div>
        <div class="{{ $montantDu > 0 ? 'bp-balance-due' : 'bp-balance-paid' }}"><span>Reste dû</span><strong>{{ number_format($montantDu, 0, ',', ' ') }} FCFA</strong></div>
    </div>

    <div class="bp-form-card mb-4">
        @if ($achat->notes)
            <p class="mb-3"><strong>Notes :</strong> {{ $achat->notes }}</p>
        @endif

        @if ($achat->statut === 'annulee')
            <div class="alert alert-warning bp-alert" role="status">
                <i class="bi bi-exclamation-triangle"></i><span>Cet achat est annulé. Aucun nouveau paiement ne peut être enregistré.</span>
            </div>
        @elseif ($montantPaye > 0)
            <div class="alert alert-info bp-alert" role="status">
                <i class="bi bi-info-circle"></i><span>L’achat a déjà reçu un paiement. Il reste enregistré; rapprochez tout remboursement séparément sans effacer les écritures historiques.</span>
            </div>
        @elseif ($montantDu > 0)
            <section class="bp-section-heading">
                <p class="bp-page-eyebrow">Règlement fournisseur</p>
                <h2 class="h5">Enregistrer un paiement</h2>
                <p class="bp-page-description">Le montant ne peut pas dépasser le solde restant de {{ number_format($montantDu, 0, ',', ' ') }} FCFA.</p>
            </section>
            <form action="{{ route('achats.paiement', $achat) }}" method="POST" class="row g-3" data-confirm data-confirm-title="Confirmer le paiement fournisseur ?" data-confirm-message="Le montant sera enregistré comme une sortie de caisse et déduit du solde restant dû." data-confirm-label="Confirmer le paiement" data-confirm-tone="danger">
                @csrf
                <div class="col-md-4">
                    <label for="montant" class="form-label">Montant (FCFA)</label>
                    <input id="montant" type="number" name="montant" class="form-control" value="{{ old('montant') }}" min="0.01" max="{{ $montantDu }}" step="0.01" data-confirm-summary="Montant" required>
                </div>
                <div class="col-md-4">
                    <label for="moyen_paiement" class="form-label">Moyen de paiement</label>
                    <select id="moyen_paiement" name="moyen_paiement" class="form-select" data-confirm-summary="Moyen" required>
                        <option value="especes" @selected(old('moyen_paiement', 'especes') === 'especes')>Espèces</option>
                        <option value="mobile_money" @selected(old('moyen_paiement') === 'mobile_money')>Mobile Money (paiement déjà effectué)</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-success"><i class="bi bi-cash-coin"></i> Enregistrer le paiement</button>
                </div>
            </form>
        @else
            <div class="bp-paid-notice"><i class="bi bi-check-circle-fill"></i><span><strong>Aucun solde restant</strong><small>Cet achat est intégralement réglé.</small></span></div>
        @endif
    </div>

    @if ($achat->statut !== 'annulee' && $montantPaye <= 0)
        <form id="annulation-achat" action="{{ route('achats.annuler', $achat) }}" method="POST" class="bp-cancel-invoice-form mb-4" data-confirm data-confirm-title="Annuler cet achat ?" data-confirm-message="L’annulation sera refusée si un paiement est déjà enregistré ou si le stock ne permet pas le retour. Les mouvements d’annulation seront conservés dans l’historique." data-confirm-label="Annuler l’achat" data-confirm-tone="danger">
            @csrf
            <label for="motif_annulation" class="form-label">Motif d’annulation</label>
            <div class="input-group">
                <input id="motif_annulation" name="motif_annulation" class="form-control" value="{{ old('motif_annulation') }}" maxlength="255" data-confirm-summary="Motif" required>
                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> Annuler l’achat</button>
            </div>
            <p class="bp-form-hint mt-2"><i class="bi bi-info-circle"></i> L’annulation est limitée aux achats sans paiement et au stock disponible.</p>
        </form>
    @endif

    <div class="table-responsive">
        <table class="table">
        <thead>
            <tr>
                <th>Produit</th>
                <th>Quantité</th>
                <th>Prix d'achat unitaire</th>
                <th>Sous-total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($achat->lignes as $ligne)
                <tr>
                    <td>{{ $ligne->produit->nom ?? '—' }}</td>
                    <td>{{ $ligne->quantite }}</td>
                    <td>{{ number_format($ligne->prix_achat_unitaire, 0, ',', ' ') }} FCFA</td>
                    <td>{{ number_format($ligne->quantite * $ligne->prix_achat_unitaire, 0, ',', ' ') }} FCFA</td>
                </tr>
            @empty
                <tr><td colspan="4" class="bp-empty-cell"><i class="bi bi-box-seam"></i><strong>Aucune ligne d'achat</strong></td></tr>
            @endforelse
        </tbody>
        </table>
    </div>

    <a href="{{ route('achats.index') }}" class="btn btn-outline-secondary">Retour</a>
@endsection
