@extends('layouts.app')

@section('title', 'Ajouter un employé - Boutik Plus')

@section('content')
    <div class="bp-page-heading">
        <div>
            <p class="bp-page-eyebrow">Accès et permissions</p>
            <h1 class="h3">Ajouter un employé</h1>
            <p class="bp-page-description">Le nouvel utilisateur aura les droits employés : consultation du stock et création de ventes.</p>
        </div>
        <a href="{{ route('utilisateurs.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour à l'équipe</a>
    </div>

    <form action="{{ route('utilisateurs.store') }}" method="POST" class="bp-form-card" data-confirm data-confirm-title="Créer ce compte employé ?" data-confirm-message="Le compte sera créé avec le rôle employé. Vérifiez le nom et l'adresse e-mail avant de confirmer." data-confirm-label="Créer le compte">
        @csrf
        <div class="mb-3">
            <label for="name" class="form-label">Nom complet</label>
            <input id="name" type="text" name="name" class="form-control" value="{{ old('name') }}" data-confirm-summary="Nom" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Adresse e-mail</label>
            <input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}" data-confirm-summary="Adresse e-mail" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Mot de passe initial</label>
            <input id="password" type="password" name="password" class="form-control" minlength="12" autocomplete="new-password" required>
        </div>
        <p class="bp-form-hint"><i class="bi bi-info-circle"></i> Utilisez au moins 12 caractères. Seul le patron peut gérer les accès de l'équipe.</p>
        <div class="bp-form-actions">
            <button type="submit" class="btn btn-success"><i class="bi bi-person-plus"></i> Créer le compte employé</button>
            <a href="{{ route('utilisateurs.index') }}" class="btn btn-outline-secondary">Annuler</a>
        </div>
    </form>
@endsection
