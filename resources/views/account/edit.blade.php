@extends('layouts.app')

@section('title', 'Mon compte - Boutik Plus')

@section('content')
    <div class="bp-page-heading">
        <div>
            <p class="bp-page-eyebrow">Sécurité et accès</p>
            <h1 class="h3">Mon compte patron</h1>
            <p class="bp-page-description">Mettez à jour votre profil et sécurisez l’accès à votre compte.</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <form action="{{ route('compte.update') }}" method="POST" class="bp-form-card">
                @csrf
                @method('PATCH')
                <h2 class="h5 mb-3">Informations personnelles</h2>
                <div class="mb-3">
                    <label for="name" class="form-label">Nom complet</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control" maxlength="255" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Adresse e-mail</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control" maxlength="255" required>
                    <small class="form-text">Une nouvelle adresse ne sera activée qu’après confirmation depuis le message qui lui sera envoyé.</small>
                </div>
                <div class="mb-3">
                    <label for="profile_current_password" class="form-label">Mot de passe actuel</label>
                    <input id="profile_current_password" type="password" name="current_password" class="form-control" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn btn-success"><i class="bi bi-check2"></i> Enregistrer le profil</button>
            </form>
        </div>

        <div class="col-12 col-xl-6">
            <form action="{{ route('compte.password.update') }}" method="POST" class="bp-form-card">
                @csrf
                @method('PUT')
                <h2 class="h5 mb-3">Changer le mot de passe</h2>
                <div class="mb-3">
                    <label for="password_current_password" class="form-label">Mot de passe actuel</label>
                    <input id="password_current_password" type="password" name="current_password" class="form-control" autocomplete="current-password" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Nouveau mot de passe</label>
                    <input id="password" type="password" name="password" class="form-control" autocomplete="new-password" minlength="12" required>
                    <small class="form-text">12 caractères minimum.</small>
                </div>
                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">Confirmer le nouveau mot de passe</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" minlength="12" required>
                </div>
                <button type="submit" class="btn btn-success"><i class="bi bi-shield-lock"></i> Changer mon mot de passe</button>
            </form>
        </div>
    </div>
@endsection
