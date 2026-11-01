@extends('layouts.app')

@section('title', 'Nouvel achat fournisseur - Boutik Plus')

@section('content')
    <div class="bp-page-heading">
        <div>
            <p class="bp-page-eyebrow">Approvisionnement et règlements</p>
            <h1 class="h3">Nouvel achat fournisseur</h1>
            <p class="bp-page-description">L'achat augmente le stock. Les paiements du fournisseur se font ensuite depuis le détail de l'achat.</p>
        </div>
        <a href="{{ route('achats.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour aux achats</a>
    </div>

    <form action="{{ route('achats.store') }}" method="POST" class="bp-form-card" data-confirm data-confirm-title="Confirmer cet achat ?" data-confirm-message="L'achat sera enregistré et les quantités ajoutées au stock. Aucun paiement fournisseur ne sera créé maintenant." data-confirm-label="Enregistrer l'achat">
        @csrf

        <div class="row">
            <div class="col-md-7 mb-3">
                <label for="fournisseur_id" class="form-label">Fournisseur</label>
                <select id="fournisseur_id" name="fournisseur_id" class="form-select" data-confirm-summary="Fournisseur" required>
                    <option value="">Choisir un fournisseur</option>
                    @foreach ($fournisseurs as $fournisseur)
                        <option value="{{ $fournisseur->id }}" @selected(old('fournisseur_id') == $fournisseur->id)>{{ $fournisseur->nom }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5 mb-3">
                <label for="date_achat" class="form-label">Date de l'achat</label>
                <input id="date_achat" type="date" name="date_achat" class="form-control" value="{{ old('date_achat', now()->toDateString()) }}" data-confirm-summary="Date" required>
            </div>
        </div>

        <section class="bp-form-section">
            <div class="bp-section-heading-row">
                <div>
                    <h2 class="h5 mb-1">Articles achetés</h2>
                    <p class="bp-page-description mb-0">Un même produit ne peut apparaître qu'une fois dans un achat.</p>
                </div>
                <button type="button" id="ajouter-ligne" class="btn btn-sm btn-outline-success" @disabled($produits->isEmpty())><i class="bi bi-plus-lg"></i> Ajouter un article</button>
            </div>
            <div id="lignes-produits">
                @foreach (old('produits', [['produit_id' => '', 'quantite' => '', 'prix_achat_unitaire' => '']]) as $index => $ligne)
                <div class="row g-2 mb-3 ligne-produit bp-purchase-line">
                    <div class="col-md-5">
                        <label class="form-label" for="produit_{{ $index }}">Produit</label>
                        <select id="produit_{{ $index }}" name="produits[{{ $index }}][produit_id]" class="form-select select-produit" data-confirm-summary="Article" required>
                            <option value="">Choisir un produit</option>
                            @foreach ($produits as $produit)
                                <option value="{{ $produit->id }}" @selected(($ligne['produit_id'] ?? '') == $produit->id)>{{ $produit->nom }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="quantite_{{ $index }}">Quantité</label>
                        <input id="quantite_{{ $index }}" type="number" name="produits[{{ $index }}][quantite]" value="{{ $ligne['quantite'] ?? '' }}" class="form-control quantite-produit" placeholder="Quantité" min="1" data-confirm-summary="Quantité" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="prix_achat_{{ $index }}">Prix d'achat unitaire (FCFA)</label>
                        <input id="prix_achat_{{ $index }}" type="number" step="0.01" name="produits[{{ $index }}][prix_achat_unitaire]" value="{{ $ligne['prix_achat_unitaire'] ?? '' }}" class="form-control prix-produit" placeholder="Prix unitaire" min="0" data-confirm-summary="Prix unitaire (FCFA)" required>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-danger bp-remove-line" aria-label="Supprimer cette ligne" @disabled($loop->first)><i class="bi bi-trash"></i></button>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="bp-total-preview" aria-live="polite">
                <span>Total de l'achat <small>Calcul estimatif, recalculé et enregistré par le serveur.</small></span>
                <strong id="total-estime">0 FCFA</strong>
            </div>
        </section>
          
        <div class="mb-3">
            <label for="notes" class="form-label">Notes <span class="text-muted">(optionnel)</span></label>
            <textarea id="notes" name="notes" class="form-control">{{ old('notes') }}</textarea>
        </div>

        @if ($fournisseurs->isEmpty() || $produits->isEmpty())
            <div class="alert alert-warning bp-alert" role="status">
                <i class="bi bi-exclamation-triangle"></i>
                <span>
                    @if ($fournisseurs->isEmpty() && $produits->isEmpty())
                        Ajoutez d'abord un fournisseur actif et un produit actif.
                    @elseif ($fournisseurs->isEmpty())
                        Ajoutez d'abord un fournisseur actif pour enregistrer un achat.
                    @else
                        Aucun produit actif n'est disponible pour cet achat.
                    @endif
                </span>
            </div>
        @endif

        <div class="bp-form-actions">
            <button type="submit" class="btn btn-success" @disabled($fournisseurs->isEmpty() || $produits->isEmpty())><i class="bi bi-bag-check"></i> Enregistrer l'achat</button>
            <a href="{{ route('achats.index') }}" class="btn btn-outline-secondary">Annuler</a>
        </div>
    </form>

    <script>
        const ligneProduits = document.getElementById('lignes-produits');
        const totalAchat = document.getElementById('total-estime');
        let index = ligneProduits.querySelectorAll('.ligne-produit').length;

        function actualiserProduitsEtTotal() {
            const lignes = [...ligneProduits.querySelectorAll('.ligne-produit')];
            const selections = lignes.map(ligne => ligne.querySelector('.select-produit'));
            const produitsChoisis = selections.map(select => select.value).filter(Boolean);

            selections.forEach(select => {
                [...select.options].forEach(option => {
                    option.disabled = option.value !== '' && option.value !== select.value && produitsChoisis.includes(option.value);
                });
            });

            const total = lignes.reduce((somme, ligne) => {
                const quantite = Number(ligne.querySelector('.quantite-produit').value || 0);
                const prix = Number(ligne.querySelector('.prix-produit').value || 0);

                return somme + quantite * prix;
            }, 0);

            totalAchat.textContent = `${new Intl.NumberFormat('fr-FR').format(total)} FCFA`;
        }

        document.getElementById('ajouter-ligne').addEventListener('click', () => {
            const conteneur = document.getElementById('lignes-produits');
            const ligne = conteneur.querySelector('.ligne-produit').cloneNode(true);
            ligne.querySelectorAll('label').forEach(label => {
                label.htmlFor = label.htmlFor.replace(/\d+$/, index);
            });
            ligne.querySelectorAll('select, input').forEach(field => {
                field.name = field.name.replace(/\[\d+\]/, `[${index}]`);
                field.id = field.id.replace(/\d+$/, index);
                field.value = '';
                field.disabled = false;
            });
            ligne.querySelector('.bp-remove-line').disabled = false;
            conteneur.appendChild(ligne);
            index++;
            actualiserProduitsEtTotal();
        });

        ligneProduits.addEventListener('click', event => {
            const button = event.target.closest('.bp-remove-line');

            if (button && ligneProduits.querySelectorAll('.ligne-produit').length > 1) {
                button.closest('.ligne-produit').remove();
                actualiserProduitsEtTotal();
            }
        });

        ligneProduits.addEventListener('input', actualiserProduitsEtTotal);
        ligneProduits.addEventListener('change', actualiserProduitsEtTotal);
        actualiserProduitsEtTotal();
    </script>
@endsection
