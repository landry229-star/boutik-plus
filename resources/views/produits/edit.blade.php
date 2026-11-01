@extends('layouts.app')

@section('title', 'Modifier un produit - Boutik Plus')

@section('content')
    <div class="bp-page-heading">
        <div>
            <p class="bp-page-eyebrow">Catalogue et inventaire</p>
            <h1 class="h3">Modifier {{ $produit->nom }}</h1>
            <p class="bp-page-description">Modifiez les informations commerciales ou enregistrez séparément un mouvement de stock justifié.</p>
        </div>
        <a href="{{ route('produits.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour au stock</a>
    </div>

    <form action="{{ route('produits.update', $produit) }}" method="POST" class="bp-form-card mb-4" data-confirm data-confirm-title="Enregistrer les modifications ?" data-confirm-message="Vérifiez les informations et les prix avant de mettre à jour ce produit." data-confirm-label="Enregistrer">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label for="nom" class="form-label">Nom du produit</label>
            <input id="nom" type="text" name="nom" class="form-control" value="{{ old('nom', $produit->nom) }}" data-confirm-summary="Nom" required>
        </div>
        <div class="mb-3">
            <label for="categorie" class="form-label">Catégorie</label>
            <input id="categorie" type="text" name="categorie" class="form-control" value="{{ old('categorie', $produit->categorie) }}">
        </div>
        <div class="mb-3">
            <label for="code_barre" class="form-label">Code-barre</label>
            <input id="code_barre" type="text" name="code_barre" class="form-control" value="{{ old('code_barre', $produit->code_barre) }}">
        </div>
        <div class="row">
            <div class="col-md mb-3">
                <label for="prix_achat" class="form-label">Prix d'achat</label>
                <input id="prix_achat" type="number" step="0.01" min="0" name="prix_achat" class="form-control" value="{{ old('prix_achat', $produit->prix_achat) }}" required>
            </div>
            <div class="col-md mb-3">
                <label for="prix_vente" class="form-label">Prix de vente</label>
                <input id="prix_vente" type="number" step="0.01" min="0" name="prix_vente" class="form-control" value="{{ old('prix_vente', $produit->prix_vente) }}" data-confirm-summary="Prix de vente (FCFA)" required>
            </div>
        </div>
        <div class="mb-3">
            <label for="seuil_alerte" class="form-label">Seuil d'alerte</label>
            <input id="seuil_alerte" type="number" min="0" name="seuil_alerte" class="form-control" value="{{ old('seuil_alerte', $produit->seuil_alerte) }}" required>
        </div>
        <button type="submit" class="btn btn-success"><i class="bi bi-check2"></i> Mettre à jour</button>
    </form>

    <section class="bp-section-heading">
        <p class="bp-page-eyebrow">Traçabilité du stock</p>
        <h2 class="h5">Enregistrer un mouvement</h2>
        <p class="bp-page-description">Toute entrée ou sortie manuelle sera ajoutée à l'historique du produit.</p>
    </section>
    <form action="{{ route('produits.mouvement', $produit) }}" method="POST" class="bp-form-card" data-confirm data-confirm-title="Enregistrer ce mouvement ?" data-confirm-message="Le stock sera ajusté et l'opération inscrite à l'historique. Vérifiez le type et la quantité." data-confirm-label="Enregistrer le mouvement">
        @csrf
        <div class="row">
            <div class="col-md mb-3">
                <label for="type" class="form-label">Type</label>
                <select id="type" name="type" class="form-select" data-confirm-summary="Mouvement">
                    <option value="entree" @selected(old('type') === 'entree')>Entrée (réapprovisionnement)</option>
                    <option value="sortie" @selected(old('type') === 'sortie')>Sortie (perte, casse...)</option>
                </select>
            </div>
            <div class="col-md mb-3">
                <label for="quantite" class="form-label">Quantité</label>
                <input id="quantite" type="number" name="quantite" class="form-control" value="{{ old('quantite') }}" min="1" data-confirm-summary="Quantité" required>
            </div>
        </div>
        <div class="mb-3">
            <label for="motif" class="form-label">Motif <span class="text-muted">(optionnel)</span></label>
            <input id="motif" type="text" name="motif" class="form-control" value="{{ old('motif') }}">
        </div>
        <button type="submit" class="btn btn-outline-success"><i class="bi bi-arrow-left-right"></i> Enregistrer le mouvement</button>
    </form>
@endsection
