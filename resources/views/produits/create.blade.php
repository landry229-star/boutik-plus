@extends('layouts.app')

@section('title', 'Ajouter un produit - Boutik Plus')

@section('content')
    <div class="bp-page-heading">
        <div>
            <p class="bp-page-eyebrow">Catalogue et inventaire</p>
            <h1 class="h3">Ajouter un produit</h1>
            <p class="bp-page-description">Renseignez les prix et le stock initial. Une entrée de stock sera enregistrée pour la traçabilité.</p>
        </div>
        <a href="{{ route('produits.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour au stock</a>
    </div>

    <form action="{{ route('produits.store') }}" method="POST" class="bp-form-card" data-confirm data-confirm-title="Créer ce produit ?" data-confirm-message="Le produit et son stock initial seront enregistrés dans le catalogue et l'historique de stock." data-confirm-label="Créer le produit">
        @csrf
        <div class="mb-3">
            <label for="nom" class="form-label">Nom du produit</label>
            <input id="nom" type="text" name="nom" class="form-control" value="{{ old('nom') }}" data-confirm-summary="Nom" required>
        </div>
        <div class="mb-3">
            <label for="categorie" class="form-label">Catégorie</label>
            <input id="categorie" type="text" name="categorie" class="form-control" value="{{ old('categorie') }}">
        </div>
        <div class="mb-3">
            <label for="code_barre" class="form-label">Code-barre <span class="text-muted">(optionnel)</span></label>
            <input id="code_barre" type="text" name="code_barre" class="form-control" value="{{ old('code_barre') }}">
        </div>
        <div class="row">
            <div class="col-md mb-3">
                <label for="prix_achat" class="form-label">Prix d'achat</label>
                <input id="prix_achat" type="number" step="0.01" min="0" name="prix_achat" class="form-control" value="{{ old('prix_achat') }}" required>
            </div>
            <div class="col-md mb-3">
                <label for="prix_vente" class="form-label">Prix de vente</label>
                <input id="prix_vente" type="number" step="0.01" min="0" name="prix_vente" class="form-control" value="{{ old('prix_vente') }}" data-confirm-summary="Prix de vente (FCFA)" required>
            </div>
        </div>
        <div class="row">
            <div class="col-md mb-3">
                <label for="quantite_stock" class="form-label">Quantité en stock</label>
                <input id="quantite_stock" type="number" min="0" name="quantite_stock" class="form-control" value="{{ old('quantite_stock', 0) }}" data-confirm-summary="Stock initial" required>
            </div>
            <div class="col-md mb-3">
                <label for="seuil_alerte" class="form-label">Seuil d'alerte</label>
                <input id="seuil_alerte" type="number" min="0" name="seuil_alerte" value="{{ old('seuil_alerte', 5) }}" class="form-control" required>
            </div>
        </div>
        <div class="bp-form-actions">
            <button type="submit" class="btn btn-success"><i class="bi bi-check2"></i> Enregistrer le produit</button>
            <a href="{{ route('produits.index') }}" class="btn btn-outline-secondary">Annuler</a>
        </div>
    </form>
@endsection
