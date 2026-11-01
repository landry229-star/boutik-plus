@extends('layouts.app')

@section('title', 'Caisse - Boutik Plus')

@section('content')
    <div class="bp-page-heading">
        <div>
            <p class="bp-page-eyebrow">Suivi des encaissements et dépenses</p>
            <h1 class="h3">Caisse</h1>
            <p class="bp-page-description">Consultez les mouvements d'une journée et vérifiez séparément les espèces et le Mobile Money.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('caisse.index') }}" class="bp-filter-card">
        <div class="row g-3 align-items-end">
            <div class="col-6 col-lg-2">
                <label for="date" class="form-label">Journée</label>
                <input id="date" type="date" name="date" value="{{ $date }}" class="form-control">
            </div>
            <div class="col-6 col-lg-2">
                <label for="type_filtre" class="form-label">Type</label>
                <select id="type_filtre" name="type" class="form-select">
                    <option value="">Tous</option>
                    <option value="entree" @selected(($filters['type'] ?? '') === 'entree')>Entrées</option>
                    <option value="sortie" @selected(($filters['type'] ?? '') === 'sortie')>Sorties</option>
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <label for="moyen_filtre" class="form-label">Moyen de paiement</label>
                <select id="moyen_filtre" name="moyen_paiement" class="form-select">
                    <option value="">Tous</option>
                    <option value="especes" @selected(($filters['moyen_paiement'] ?? '') === 'especes')>Espèces</option>
                    <option value="mobile_money" @selected(($filters['moyen_paiement'] ?? '') === 'mobile_money')>Mobile Money</option>
                </select>
            </div>
            <div class="col-6 col-lg-3">
                <label for="q" class="form-label">Description</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="Rechercher">
            </div>
            <div class="col-12 col-lg-2 d-flex gap-2">
                <a href="{{ route('caisse.index', ['date' => $date]) }}" class="btn btn-outline-secondary">Effacer</a>
                <button class="btn btn-success flex-grow-1" type="submit"><i class="bi bi-funnel"></i> Filtrer</button>
                <button class="btn btn-outline-danger" type="submit" name="pdf" value="1"><i class="bi bi-file-earmark-pdf"></i></button>
            </div>
        </div>
    </form>
    <p class="bp-form-hint mb-3"><i class="bi bi-info-circle"></i> Les cartes affichent les totaux complets de la journée ; les filtres ne réduisent que la liste. Le journal n’est pas modifiable : annulez une saisie manuelle par écriture inverse, puis enregistrez la correction.</p>

    <div class="row mb-4 gy-3">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card bp-stat-card bp-stat-day"><div class="card-body"><div class="bp-stat-heading">Entrées espèces <i class="bi bi-arrow-down-left-circle"></i></div><div class="bp-stat-value">{{ number_format($totalEntrees, 0, ',', ' ') }} <span>FCFA</span></div><div class="bp-stat-caption">Encaissements de la journée</div></div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card bp-stat-card bp-stat-stock"><div class="card-body"><div class="bp-stat-heading">Sorties espèces <i class="bi bi-arrow-up-right-circle"></i></div><div class="bp-stat-value">{{ number_format($totalSorties, 0, ',', ' ') }} <span>FCFA</span></div><div class="bp-stat-caption">Dépenses et paiements enregistrés</div></div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card bp-stat-card bp-stat-month"><div class="card-body"><div class="bp-stat-heading">Solde espèces <i class="bi bi-wallet2"></i></div><div class="bp-stat-value">{{ number_format($solde, 0, ',', ' ') }} <span>FCFA</span></div><div class="bp-stat-caption">Entrées moins sorties espèces</div></div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card bp-stat-card"><div class="card-body"><div class="bp-stat-heading">Solde Mobile Money <i class="bi bi-phone"></i></div><div class="bp-stat-value">{{ number_format($totalMobileMoney, 0, ',', ' ') }} <span>FCFA</span></div><div class="bp-stat-caption">Solde net Mobile Money de la journée</div></div></div>
        </div>
    </div>

    <section class="bp-section-heading">
        <p class="bp-page-eyebrow">Nouvelle opération</p>
        <h2 class="h5">Enregistrer un mouvement</h2>
        <p class="bp-page-description">Saisissez uniquement les opérations non déjà importées d'une vente ou d'un achat.</p>
    </section>
    <form action="{{ route('caisse.store') }}" method="POST" class="bp-form-card mb-4" data-confirm data-confirm-title="Enregistrer ce mouvement de caisse ?" data-confirm-message="Le mouvement modifiera le solde de la journée et sera inscrit à l'historique. Vérifiez le type, le moyen de paiement et le montant." data-confirm-label="Enregistrer le mouvement">
        @csrf
        <div class="row">
            <div class="col-md mb-3">
                <label for="type" class="form-label">Type de mouvement</label>
                <select id="type" name="type" class="form-select" data-confirm-summary="Type">
                    <option value="entree" @selected(old('type', 'entree') === 'entree')>Entrée</option>
                    <option value="sortie" @selected(old('type') === 'sortie')>Sortie</option>
                </select>
            </div>
            <div class="col-md mb-3">
                <label for="moyen_paiement" class="form-label">Moyen de paiement</label>
                <select id="moyen_paiement" name="moyen_paiement" class="form-select" data-confirm-summary="Moyen de paiement" required>
                    <option value="especes" @selected(old('moyen_paiement', 'especes') === 'especes')>Espèces</option>
                    <option value="mobile_money" @selected(old('moyen_paiement') === 'mobile_money')>Mobile Money (opération déjà effectuée)</option>
                </select>
            </div>
            <div class="col-md mb-3">
                <label for="montant" class="form-label">Montant (FCFA)</label>
                <input id="montant" type="number" step="0.01" min="0.01" name="montant" value="{{ old('montant') }}" class="form-control" data-confirm-summary="Montant (FCFA)" required>
            </div>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description <span class="text-muted">(recommandée)</span></label>
            <input id="description" type="text" name="description" value="{{ old('description') }}" class="form-control" maxlength="255" data-confirm-summary="Description">
        </div>
        <button type="submit" class="btn btn-success"><i class="bi bi-check2"></i> Enregistrer le mouvement</button>
    </form>

    <div class="table-responsive">
        <table class="table">
        <thead>
            <tr>
                <th>Heure</th>
                <th>Type</th>
                <th>Moyen</th>
                <th>Montant</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($mouvements as $mouvement)
                <tr>
                    <td>{{ $mouvement->created_at->format('H:i') }}</td>
                    <td>{{ $mouvement->type === 'entree' ? 'Entrée' : 'Sortie' }}</td>
                    <td>{{ $mouvement->moyen_paiement === 'mobile_money' ? 'Mobile Money' : 'Espèces' }}</td>
                    <td>{{ number_format($mouvement->montant, 0, ',', ' ') }} FCFA</td>
                    <td>{{ $mouvement->description }}</td>
                    <td>
                        @if ($mouvement->mouvement_origine_id)
                            <span class="badge text-bg-secondary">Correction de #{{ $mouvement->mouvement_origine_id }}</span>
                        @elseif ($mouvement->annulation)
                            <span class="badge text-bg-secondary">Annulé</span>
                            <form action="{{ route('caisse.supprimer-definitivement', $mouvement) }}" method="POST" class="d-inline" data-confirm data-confirm-title="Supprimer définitivement ce mouvement annulé ?" data-confirm-message="Le mouvement et son écriture d’annulation seront effacés ensemble. Cette action est irréversible." data-confirm-label="Supprimer définitivement" data-confirm-tone="danger">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> Supprimer</button>
                            </form>
                        @elseif ($mouvement->facture_id)
                            <a href="{{ route('factures.show', $mouvement->facture_id) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-receipt"></i> Voir la facture</a>
                        @elseif ($mouvement->achat_id)
                            <a href="{{ route('achats.show', $mouvement->achat_id) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-bag"></i> Voir l’achat</a>
                        @else
                            <form action="{{ route('caisse.annuler', $mouvement) }}" method="POST" class="d-inline" data-confirm data-confirm-title="Annuler ce mouvement ?" data-confirm-message="Une écriture inverse de {{ number_format($mouvement->montant, 0, ',', ' ') }} FCFA sera ajoutée à la même journée. L’historique d’origine ne sera pas supprimé." data-confirm-label="Créer l’écriture inverse" data-confirm-tone="danger">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-arrow-counterclockwise"></i> Annuler</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="bp-empty-cell"><i class="bi bi-cash-stack"></i><strong>Aucun mouvement pour cette journée</strong><span>Les ventes et opérations enregistrées apparaîtront ici.</span></td></tr>
            @endforelse
        </tbody>
        </table>
    </div>
    {{ $mouvements->links() }}
@endsection
