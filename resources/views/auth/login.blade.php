<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion - Boutik Plus</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/boutik-plus.css') }}" rel="stylesheet">
    @include('partials.pwa-head')
</head>
<body class="bp-login-body">
    <main class="bp-login-shell">
        <section class="bp-login-showcase" aria-label="Présentation de Boutik Plus">
            <a href="{{ route('vitrine') }}" class="bp-login-home"><i class="bi bi-arrow-left"></i> Retour à l’accueil</a>
            <div class="bp-showcase-content">
                <span class="bp-showcase-icon"><i class="bi bi-shop-window"></i></span>
                <p class="bp-showcase-eyebrow">Votre commerce, mieux organisé</p>
                <h1>La gestion de votre boutique, en toute simplicité.</h1>
                <p class="bp-showcase-description">Suivez vos produits, vos ventes et votre caisse depuis un seul espace.</p>
                <div class="bp-showcase-features">
                    <span><i class="bi bi-box-seam"></i> Stock maîtrisé</span>
                    <span><i class="bi bi-receipt"></i> Ventes suivies</span>
                    <span><i class="bi bi-shield-check"></i> Accès sécurisé</span>
                </div>
            </div>
            <p class="bp-showcase-footer">Un outil pensé pour les commerces du quotidien.</p>
        </section>

        <section class="bp-login-panel">
            <div class="bp-login-card">
                <div class="bp-login-heading">
                    <img src="{{ asset('icons/icon-192.png') }}" alt="" class="bp-login-logo">
                    <div>
                        <p class="bp-login-kicker">Espace de gestion</p>
                        <h2>Content de vous revoir</h2>
                        <p class="bp-login-subtitle">Connectez-vous pour continuer.</p>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-success btn-sm w-100 mb-3" data-pwa-install>Installer Boutik Plus</button>

                @if ($errors->any())
                    <div class="alert alert-danger bp-login-error" role="alert">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('login.store') }}" method="POST" class="bp-login-form">
                    @csrf
                    <div class="mb-4">
                        <label for="email" class="form-label">Adresse e-mail</label>
                        <div class="input-group bp-input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="nom@exemple.com" autocomplete="username" required autofocus>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Mot de passe</label>
                        <div class="input-group bp-input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input id="password" type="password" name="password" class="form-control" placeholder="Votre mot de passe" autocomplete="current-password" required>
                            <button class="input-group-text bp-password-toggle" type="button" aria-label="Afficher le mot de passe" aria-pressed="false">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="bp-login-options">
                        <div class="form-check">
                            <input type="checkbox" name="remember" class="form-check-input" id="remember" value="1">
                            <label class="form-check-label" for="remember">Se souvenir de moi</label>
                        </div>
                        <span><i class="bi bi-shield-lock"></i> Connexion sécurisée</span>
                    </div>
                    <button type="submit" class="btn btn-success bp-login-submit">
                        <span>Se connecter</span><i class="bi bi-arrow-right"></i>
                    </button>
                </form>
                @if (session('success'))
                    <div class="alert alert-success" role="status">{{ session('success') }}</div>
                @endif
                <p class="text-center mt-3 mb-2"><a href="{{ route('password.request') }}">Mot de passe oublié ?</a></p>
                <p class="bp-login-help">Besoin d’aide ? Contactez le responsable de votre boutique.</p>
            </div>
            <footer class="bp-login-copyright">© {{ date('Y') }} Boutik Plus</footer>
        </section>
    </main>
    <script>
        const passwordInput = document.getElementById('password');
        const passwordToggle = document.querySelector('.bp-password-toggle');

        passwordToggle.addEventListener('click', () => {
            const isPasswordVisible = passwordInput.type === 'password';
            passwordInput.type = isPasswordVisible ? 'text' : 'password';
            passwordToggle.setAttribute('aria-pressed', String(isPasswordVisible));
            passwordToggle.setAttribute('aria-label', isPasswordVisible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
            passwordToggle.innerHTML = `<i class="bi ${isPasswordVisible ? 'bi-eye-slash' : 'bi-eye'}"></i>`;
        });
    </script>
</body>
</html>
