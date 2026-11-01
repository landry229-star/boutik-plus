@extends('layouts.app')

@section('title', 'Modifier un fournisseur - Boutik Plus')

@section('content')
    <div class="bp-page-heading">
        <div>
            <p class="bp-page-eyebrow">Partenaires commerciaux</p>
            <h1 class="h3">Modifier {{ $fournisseur->nom }}</h1>
            <p class="bp-page-description">Les coordonnées actualisées seront utilisées dans les prochains achats.</p>
        </div>
        <a href="{{ route('fournisseurs.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour aux fournisseurs</a>
    </div>

    <form action="{{ route('fournisseurs.update', $fournisseur) }}" method="POST" class="bp-form-card" data-confirm data-confirm-title="Enregistrer les coordonnées ?" data-confirm-message="Les informations de ce fournisseur seront mises à jour sans modifier son historique d'achats." data-confirm-label="Enregistrer">
        @csrf
        @method('PUT')

        <div class="mb-3">
            <label for="nom" class="form-label">Nom du fournisseur</label>
            <input id="nom" type="text" name="nom" class="form-control" value="{{ old('nom', $fournisseur->nom) }}" data-confirm-summary="Nom" required>
        </div>

        <div class="mb-3">
            <label for="telephone" class="form-label">Téléphone</label>
            <input id="telephone" type="text" name="telephone" class="form-control" value="{{ old('telephone', $fournisseur->telephone) }}">
        </div>

        <div class="mb-3">
            <label for="adresse" class="form-label">Adresse</label>
            <input id="adresse" type="text" name="adresse" class="form-control" value="{{ old('adresse', $fournisseur->adresse) }}">
        </div>

        <div class="mb-3">
            <label for="notes" class="form-label">Notes</label>
            <textarea id="notes" name="notes" class="form-control">{{ old('notes', $fournisseur->notes) }}</textarea>
        </div>

        <div class="bp-form-actions">
            <button type="submit" class="btn btn-success"><i class="bi bi-check2"></i> Enregistrer</button>
            <a href="{{ route('fournisseurs.index') }}" class="btn btn-outline-secondary">Annuler</a>
        </div>
    </form>
@endsection
