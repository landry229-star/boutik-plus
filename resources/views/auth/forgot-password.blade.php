<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Récupération du compte - Boutik Plus</title>
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
                        <h1 class="h3">Mot de passe oublié ?</h1>
                        <p class="bp-login-subtitle">Saisissez l’adresse e-mail associée à votre compte. Si elle existe, nous lui enverrons un lien temporaire.</p>
                    </div>
                </div>

                @if (session('status'))
                    <div class="alert alert-success" role="status">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form action="{{ route('password.email') }}" method="POST" class="bp-login-form">
                    @csrf
                    <div class="mb-4">
                        <label for="email" class="form-label">Adresse e-mail</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control" autocomplete="email" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-success bp-login-submit">
                        <span>Envoyer le lien de récupération</span><i class="bi bi-envelope"></i>
                    </button>
                </form>
                <p class="text-center mt-3 mb-0"><a href="{{ route('login') }}">Retour à la connexion</a></p>
            </div>
        </section>
    </main>
</body>
</html>
