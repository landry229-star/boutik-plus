<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Réinitialiser le mot de passe - Boutik Plus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/boutik-plus.css') }}" rel="stylesheet">
</head>
<body class="bp-login-body">
    <main class="bp-login-shell">
        <section class="bp-login-panel">
            <div class="bp-login-card">
                <div class="bp-login-heading">
                    <img src="{{ asset('icons/icon-192.png') }}" alt="" class="bp-login-logo">
                    <div>
                        <p class="bp-login-kicker">Récupération sécurisée</p>
                        <h1 class="h3">Choisir un nouveau mot de passe</h1>
                        <p class="bp-login-subtitle">Utilisez un mot de passe d’au moins 12 caractères.</p>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form action="{{ route('password.update') }}" method="POST" class="bp-login-form">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div class="mb-3">
                        <label for="email" class="form-label">Adresse e-mail</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $email) }}" class="form-control" autocomplete="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Nouveau mot de passe</label>
                        <input id="password" type="password" name="password" class="form-control" autocomplete="new-password" minlength="12" required>
                    </div>
                    <div class="mb-4">
                        <label for="password_confirmation" class="form-label">Confirmer le mot de passe</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" minlength="12" required>
                    </div>
                    <button type="submit" class="btn btn-success bp-login-submit">
                        <span>Réinitialiser le mot de passe</span><i class="bi bi-shield-check"></i>
                    </button>
                </form>
                <p class="text-center mt-3 mb-0"><a href="{{ route('login') }}">Retour à la connexion</a></p>
            </div>
        </section>
    </main>
</body>
</html>
