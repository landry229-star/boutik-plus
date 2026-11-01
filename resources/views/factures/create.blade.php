@extends('layouts.app')

@section('title', 'Nouvelle facture - Boutik Plus')

@section('content')
    <div class="bp-page-heading">
        <div>
            <p class="bp-page-eyebrow">Ventes et encaissements</p>
            <h1 class="h3">Nouvelle facture</h1>
            <p class="bp-page-description">Les quantités seront déduites du stock et le paiement enregistré dans la caisse après confirmation.</p>
        </div>
        <a href="{{ route('factures.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour aux factures</a>
    </div>

    <form action="{{ route('factures.store') }}" method="POST" class="bp-form-card" data-confirm data-confirm-title="Confirmer la vente ?" data-confirm-message="Les ventes en espèces ou déjà encaissées sont validées immédiatement. Un paiement FedaPay réserve le stock et reste en attente jusqu'à confirmation." data-confirm-label="Continuer">
        @csrf
        <section class="bp-form-section">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="client_nom" class="form-label">Nom du client <span class="text-muted">(optionnel)</span></label>
                    <input id="client_nom" type="text" name="client_nom" class="form-control" value="{{ old('client_nom') }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="client_telephone" class="form-label">Téléphone du client <span class="text-muted">(pour WhatsApp)</span></label>
                    <input id="client_telephone" type="text" name="client_telephone" class="form-control" value="{{ old('client_telephone') }}" placeholder="Ex. : 22997123456">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="client_email" class="form-label">E-mail du client <span class="text-muted">(pour l'envoi de facture)</span></label>
                    <input id="client_email" type="email" name="client_email" class="form-control" value="{{ old('client_email') }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="mode_paiement" class="form-label">Mode de paiement</label>
                    <select id="mode_paiement" name="mode_paiement" class="form-select" data-confirm-summary="Paiement">
                        <option value="especes" @selected(old('mode_paiement', 'especes') === 'especes')>Espèces</option>
                        <option value="mobile_money" @selected(old('mode_paiement') === 'mobile_money')>Mobile Money déjà encaissé</option>
                        <option value="fedapay" @selected(old('mode_paiement') === 'fedapay') @disabled(! $fedapayAvailable)>Payer en Mobile Money via FedaPay{{ $fedapayAvailable ? '' : ' (non configuré)' }}</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="bp-form-section">
            <div class="bp-section-heading-row">
                <div>
                    <h2 class="h5 mb-1">Articles vendus</h2>
                    <p class="bp-page-description mb-0">Le prix de vente actuel du catalogue sera utilisé.</p>
                </div>
                <button type="button" id="ajouter-ligne" class="btn btn-sm btn-outline-success" @disabled($produits->isEmpty())><i class="bi bi-plus-lg"></i> Ajouter un article</button>
            </div>
            <div id="lignes-produits">
                @foreach (old('produits', [['id' => '', 'quantite' => '']]) as $ligneIndex => $ligne)
                    <div class="row g-2 mb-2 ligne-produit">
                        <div class="col-md-8">
                            <label class="form-label" for="produit_{{ $ligneIndex }}">Produit</label>
                            <select id="produit_{{ $ligneIndex }}" name="produits[{{ $ligneIndex }}][id]" class="form-select select-produit" data-confirm-summary="Article" required>
                                <option value="">Choisir un produit</option>
                                @foreach ($produits as $produit)
                                    <option value="{{ $produit->id }}" data-price="{{ $produit->prix_vente }}" @selected(($ligne['id'] ?? '') == $produit->id)>{{ $produit->nom }} — {{ number_format($produit->prix_vente, 0, ',', ' ') }} FCFA (stock : {{ $produit->quantite_stock }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="quantite_{{ $ligneIndex }}">Quantité</label>
                            <input id="quantite_{{ $ligneIndex }}" type="number" name="produits[{{ $ligneIndex }}][quantite]" class="form-control quantite-produit" placeholder="Quantité" min="1" value="{{ $ligne['quantite'] ?? '' }}" data-confirm-summary="Quantité" required>
                        </div>
                        <div class="col-12 d-flex justify-content-end"><button type="button" class="btn btn-sm btn-outline-danger bp-remove-line" aria-label="Supprimer cette ligne" @disabled($loop->first)><i class="bi bi-trash"></i> Retirer l'article</button></div>
                    </div>
                @endforeach
            </div>
            <div class="bp-total-preview" data-tax-rate="{{ config('boutik.tax.enabled') ? config('boutik.tax.rate') : 0 }}" aria-live="polite">
                <span>Total estimé <small id="detail-total-estime">Hors taxe : 0 FCFA. Le total définitif est recalculé côté serveur.</small></span>
                <strong id="total-estime">0 FCFA TTC</strong>
            </div>
        </section>

        @if ($produits->isEmpty())
            <div class="alert alert-warning bp-alert" role="status"><i class="bi bi-exclamation-triangle"></i><span>Aucun article n'est actuellement disponible à la vente. Ajoutez du stock avant de créer une facture.</span></div>
        @endif
        @if ($produits->isNotEmpty())
            <div class="bp-inline-notice"><i class="bi bi-info-circle"></i> « Mobile Money déjà encaissé » enregistre un paiement reçu hors application. FedaPay ouvre un paiement sécurisé et la facture n'est validée qu'après confirmation du prestataire.</div>
        @endif

        <div class="bp-form-actions">
            <button type="submit" class="btn btn-success" @disabled($produits->isEmpty())><i class="bi bi-receipt"></i> Enregistrer la vente</button>
            <a href="{{ route('factures.index') }}" class="btn btn-outline-secondary">Annuler</a>
        </div>
    </form>

    <script>
        const lignesProduits = document.getElementById('lignes-produits');
        const totalEstime = document.getElementById('total-estime');
        const detailTotalEstime = document.getElementById('detail-total-estime');
        const tauxTaxe = Number(document.querySelector('.bp-total-preview').dataset.taxRate || 0);
        const formateurMontant = new Intl.NumberFormat('fr-FR');
        let index = lignesProduits.querySelectorAll('.ligne-produit').length;

        function actualiserTotal() {
            const total = [...lignesProduits.querySelectorAll('.ligne-produit')].reduce((somme, ligne) => {
                const produit = ligne.querySelector('.select-produit').selectedOptions[0];
                const prix = Number(produit?.dataset.price || 0);
                const quantite = Number(ligne.querySelector('.quantite-produit').value || 0);

                return somme + prix * quantite;
            }, 0);

            const montantTaxe = Math.round((total * tauxTaxe / 100 + Number.EPSILON) * 100) / 100;
            const totalTtc = total + montantTaxe;

            detailTotalEstime.textContent = tauxTaxe > 0
                ? `Hors taxe : ${formateurMontant.format(total)} FCFA · Taxe (${tauxTaxe} %) : ${formateurMontant.format(montantTaxe)} FCFA`
                : `Hors taxe : ${formateurMontant.format(total)} FCFA`;
            totalEstime.textContent = `${formateurMontant.format(totalTtc)} FCFA TTC`;
        }

        document.getElementById('ajouter-ligne').addEventListener('click', () => {
            const conteneur = document.getElementById('lignes-produits');
            const ligne = conteneur.querySelector('.ligne-produit').cloneNode(true);
            ligne.querySelectorAll('select, input').forEach(champ => {
                champ.name = champ.name.replace(/\[\d+\]/, `[${index}]`);
                champ.id = champ.id.replace(/\d+$/, index);
                champ.value = '';
            });
            ligne.querySelectorAll('label').forEach(label => {
                label.htmlFor = label.htmlFor.replace(/\d+$/, index);
            });
            ligne.querySelector('.bp-remove-line').disabled = false;
            conteneur.appendChild(ligne);
            index++;
            actualiserTotal();
        });

        lignesProduits.addEventListener('input', actualiserTotal);
        lignesProduits.addEventListener('change', actualiserTotal);
        lignesProduits.addEventListener('click', event => {
            const button = event.target.closest('.bp-remove-line');

            if (button && lignesProduits.querySelectorAll('.ligne-produit').length > 1) {
                button.closest('.ligne-produit').remove();
                actualiserTotal();
            }
        });
        actualiserTotal();
    </script>
@endsection
