@extends('layouts.app')

@section('title', 'Ajouter un fournisseur - Boutik Plus')

@section('content')
    <div class="bp-page-heading">
        <div>
            <p class="bp-page-eyebrow">Partenaires commerciaux</p>
            <h1 class="h3">Ajouter un fournisseur</h1>
            <p class="bp-page-description">Enregistrez les coordonnées utilisées pour vos achats et votre suivi fournisseur.</p>
        </div>
        <a href="{{ route('fournisseurs.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour aux fournisseurs</a>
    </div>

    <form action="{{ route('fournisseurs.store') }}" method="POST" class="bp-form-card" data-confirm data-confirm-title="Ajouter ce fournisseur ?" data-confirm-message="Le fournisseur sera ajouté à votre liste et pourra être sélectionné lors d'un achat." data-confirm-label="Ajouter le fournisseur">
        @csrf

        <div class="mb-3">
            <label for="nom" class="form-label">Nom du fournisseur</label>
            <input id="nom" type="text" name="nom" class="form-control" value="{{ old('nom') }}" data-confirm-summary="Nom" required>
        </div>

        <div class="mb-3">
            <label for="telephone" class="form-label">Téléphone</label>
            <input id="telephone" type="text" name="telephone" class="form-control" value="{{ old('telephone') }}">
        </div>

        <div class="mb-3">
            <label for="adresse" class="form-label">Adresse</label>
            <input id="adresse" type="text" name="adresse" class="form-control" value="{{ old('adresse') }}">
        </div>

        <div class="mb-3">
            <label for="notes" class="form-label">Notes <span class="text-muted">(optionnel)</span></label>
            <textarea id="notes" name="notes" class="form-control">{{ old('notes') }}</textarea>
        </div>

        <div class="bp-form-actions">
            <button type="submit" class="btn btn-success"><i class="bi bi-check2"></i> Enregistrer le fournisseur</button>
            <a href="{{ route('fournisseurs.index') }}" class="btn btn-outline-secondary">Annuler</a>
        </div>
    </form>
@endsection
