@extends('layouts.app')

@section('title', 'Détail de facture - Boutik Plus')

@section('content')
    <div class="bp-page-heading">
        <div>
            <p class="bp-page-eyebrow">Ventes et encaissements</p>
            <h1 class="h3">Facture {{ $facture->numero }}</h1>
            <p class="bp-page-description">Émise le {{ $facture->created_at->format('d/m/Y à H:i') }} <span class="bp-inline-separator">·</span> {{ $facture->client_nom ?? 'Client comptant' }}</p>
        </div>
        <a href="{{ route('factures.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour aux factures</a>
    </div>

    <div class="bp-detail-card">
        <section class="bp-form-card mb-3">
            <h2 class="h6 mb-2">{{ $company['name'] ?: config('app.name') }}</h2>
            @if (filled($company['address'] ?? null))
                <div>{{ $company['address'] }}</div>
            @endif
            @if (filled($company['phone'] ?? null))
                <div>Téléphone : {{ $company['phone'] }}</div>
            @endif
            @if (filled($company['email'] ?? null))
                <div>E-mail : {{ $company['email'] }}</div>
            @endif
            @if (filled($company['ifu'] ?? null))
                <div>IFU : {{ $company['ifu'] }}</div>
            @endif
            @if (filled($company['rccm'] ?? null))
                <div>RCCM : {{ $company['rccm'] }}</div>
            @endif
        </section>

        <div class="bp-detail-meta">
            <span><i class="bi bi-person"></i> {{ $facture->client_nom ?? 'Client comptant' }}</span>
            <span><i class="bi bi-credit-card"></i> {{ $facture->mode_paiement === 'mobile_money' ? 'Mobile Money' : 'Espèces' }}</span>
            <span><i class="bi bi-person-badge"></i> {{ $facture->utilisateur->name ?? $facture->utilisateur_nom_snapshot ?? '—' }}</span>
            <span @class([
                'badge',
                'text-bg-secondary' => $facture->statut === 'annulee',
                'text-bg-warning' => $facture->statut === 'en_attente',
                'bp-badge-success' => $facture->statut === 'payee',
            ])>{{ $facture->statut === 'annulee' ? 'Annulée' : ($facture->statut === 'en_attente' ? 'Paiement en attente' : 'Payée') }}</span>
        </div>

        <div class="table-responsive mt-3">
            <table class="table">
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Quantité</th>
                    <th>Prix unitaire</th>
                    <th>Sous-total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($facture->lignes as $ligne)
                    <tr>
                        <td>{{ $ligne->produit->nom }}</td>
                        <td>{{ $ligne->quantite }}</td>
                        <td>{{ number_format($ligne->prix_unitaire, 0, ',', ' ') }} {{ $currency }}</td>
                        <td>{{ number_format($ligne->sous_total, 0, ',', ' ') }} {{ $currency }}</td>
                    </tr>
                @endforeach
            </tbody>
            </table>
        </div>

        @if ($facture->montant_taxe > 0)
            <p class="text-end mb-1">Montant hors taxe : {{ number_format($facture->montant_ht, 0, ',', ' ') }} {{ $currency }}</p>
            <p class="text-end mb-1">{{ $facture->libelle_taxe ?: 'Taxe' }} ({{ number_format($facture->taux_taxe, 2, ',', ' ') }} %) :
                {{ number_format($facture->montant_taxe, 0, ',', ' ') }} {{ $currency }}</p>
        @endif
        <div class="bp-invoice-total"><span>Total TTC</span><strong>{{ number_format($facture->montant_total, 0, ',', ' ') }} {{ $currency }}</strong></div>

        @if ($facture->statut === 'annulee')
            <div class="alert alert-warning bp-alert" role="status">
                <i class="bi bi-exclamation-triangle"></i><span>Facture annulée le {{ $facture->annulee_at?->format('d/m/Y H:i') }}. Motif : {{ $facture->motif_annulation }}</span>
            </div>
        @elseif ($facture->statut === 'en_attente')
            <div class="alert alert-info bp-alert" role="status">
                <i class="bi bi-hourglass-split"></i>
                <span>Le paiement FedaPay est en attente. Le stock est réservé et aucun encaissement ne sera ajouté avant confirmation.</span>
            </div>
            @foreach ($facture->paiementsMobileMoney as $paiement)
                @if (filled($paiement->response['checkout_url'] ?? null))
                    <a href="{{ $paiement->response['checkout_url'] }}" class="btn btn-success mb-2" target="_blank" rel="noopener noreferrer">Reprendre le paiement FedaPay</a>
                @endif
            @endforeach
            @if ($facture->paiementsMobileMoney->contains(fn ($paiement) => $paiement->status === 'pending' && filled($paiement->external_reference)))
                <form action="{{ route('factures.paiement.reprendre', $facture) }}" method="POST" class="mb-2">
                    @csrf
                    <button type="submit" class="btn btn-outline-success"><i class="bi bi-arrow-repeat"></i> Régénérer le lien FedaPay</button>
                </form>
            @endif
            @if ($facture->paiementsMobileMoney->contains(fn ($paiement) => $paiement->status === 'pending' && filled($paiement->external_reference)))
                <form action="{{ route('factures.paiement.verifier', $facture) }}" method="POST" class="mb-3">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-arrow-clockwise"></i> Vérifier le paiement</button>
                </form>
            @elseif ($facture->paiementsMobileMoney->contains(fn ($paiement) => $paiement->status === 'pending'))
                <p class="text-muted">La création de la transaction est en cours de vérification. Ne relancez pas la vente ; le stock reste réservé.</p>
            @endif
        @elseif ($facture->statut === 'payee' && $facture->paiementsMobileMoney->contains(fn ($paiement) => $paiement->provider === 'fedapay' && $paiement->status === 'approved'))
            <div class="alert alert-info bp-alert" role="status">
                <i class="bi bi-info-circle"></i>
                <span>Les remboursements FedaPay ne sont pas intégrés. Cette facture ne peut pas être annulée dans Boutik Plus ; effectuez tout remboursement dans le tableau de bord marchand et conservez cette facture pour l’audit.</span>
            </div>
        @elseif (auth()->user()->estPatron())
            <form action="{{ route('factures.annuler', $facture) }}" method="POST" class="bp-cancel-invoice-form" data-confirm data-confirm-title="Annuler cette facture ?" data-confirm-message="Le montant sera annulé en caisse et le stock des articles réintégré. Cette opération sera inscrite à l'historique." data-confirm-label="Annuler la facture" data-confirm-tone="danger">
                @csrf
                <label for="motif_annulation" class="form-label">Motif d'annulation</label>
                <div class="input-group">
                    <input id="motif_annulation" name="motif_annulation" class="form-control" value="{{ old('motif_annulation') }}" maxlength="255" data-confirm-summary="Motif" required>
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> Annuler la facture</button>
                </div>
            </form>
        @endif

        <div class="bp-detail-actions">
            <a href="{{ route('factures.pdf', ['facture' => $facture, 'format' => 'complet']) }}" class="btn btn-outline-primary">PDF détaillé</a>
            <a href="{{ route('factures.pdf', ['facture' => $facture, 'format' => 'simple']) }}" class="btn btn-outline-secondary">PDF simple</a>

            @if ($facture->statut === 'annulee' && auth()->user()->estPatron())
                <form action="{{ route('factures.supprimer-definitivement', $facture) }}" method="POST" data-confirm data-confirm-title="Supprimer définitivement cette facture ?" data-confirm-message="La facture annulée, ses lignes, ses paiements Mobile Money et ses mouvements de caisse et de stock liés seront effacés. Cette action est irréversible." data-confirm-label="Supprimer définitivement" data-confirm-tone="danger">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Supprimer définitivement</button>
                </form>
            @endif

            @if ($facture->statut === 'payee' && $facture->client_telephone)
                @php
                    $numero = preg_replace('/[^0-9]/', '', $facture->client_telephone);
                    $message = "Bonjour, voici votre facture " . $facture->numero . ". Total : " . number_format($facture->montant_total, 0, ',', ' ') . " FCFA. Merci pour votre achat !";
                @endphp
                <a href="https://wa.me/{{ $numero }}?text={{ urlencode($message) }}" target="_blank" rel="noopener noreferrer" class="btn btn-success" data-confirm-link data-confirm-title="Ouvrir WhatsApp ?" data-confirm-message="Un message contenant le numéro et le total de la facture sera préparé dans WhatsApp. Vérifiez le destinataire avant l'envoi." data-confirm-label="Ouvrir WhatsApp">Envoyer par WhatsApp</a>
            @endif

            @if ($facture->statut === 'payee' && $facture->client_email)
                <form action="{{ route('factures.envoyer-email', $facture) }}" method="POST" data-confirm data-confirm-title="Envoyer cette facture ?" data-confirm-message="La facture PDF sera envoyée à l'adresse e-mail enregistrée pour ce client." data-confirm-label="Envoyer l'e-mail">
                    @csrf
                    <button type="submit" class="btn btn-outline-success"><i class="bi bi-envelope"></i> Envoyer par e-mail</button>
                </form>
            @endif
        </div>
    </div>
@endsection
