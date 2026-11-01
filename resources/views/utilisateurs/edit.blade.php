@extends('layouts.app')

@section('title', 'Modifier un employé - Boutik Plus')

@section('content')
    <div class="bp-page-heading">
        <div>
            <p class="bp-page-eyebrow">Accès et permissions</p>
            <h1 class="h3">Modifier {{ $utilisateur->name }}</h1>
            <p class="bp-page-description">Mettez à jour les coordonnées de l’employé sans modifier son historique d’activité.</p>
        </div>
        <a href="{{ route('utilisateurs.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour à l'équipe</a>
    </div>

    <form action="{{ route('utilisateurs.update', $utilisateur) }}" method="POST" class="bp-form-card" data-confirm data-confirm-title="Enregistrer ces modifications ?" data-confirm-message="Le nom et l’adresse e-mail de cet employé seront mis à jour. Son historique restera inchangé." data-confirm-label="Enregistrer">
        @csrf
        @method('PATCH')
        <div class="mb-3">
            <label for="name" class="form-label">Nom complet</label>
            <input id="name" type="text" name="name" class="form-control" value="{{ old('name', $utilisateur->name) }}" data-confirm-summary="Nom" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Adresse e-mail</label>
            <input id="email" type="email" name="email" class="form-control" value="{{ old('email', $utilisateur->email) }}" data-confirm-summary="Adresse e-mail" required>
        </div>
        <p class="bp-form-hint"><i class="bi bi-info-circle"></i> Le mot de passe n’est pas modifié depuis cette page.</p>
        <div class="bp-form-actions">
            <button type="submit" class="btn btn-success"><i class="bi bi-check2"></i> Enregistrer</button>
            <a href="{{ route('utilisateurs.index') }}" class="btn btn-outline-secondary">Annuler</a>
        </div>
    </form>
@endsection
