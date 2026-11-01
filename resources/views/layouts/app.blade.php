<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Boutik Plus')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="{{ asset('css/boutik-plus.css') }}" rel="stylesheet">

    @include('partials.pwa-head')
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bp-navbar">
        <div class="container-fluid bp-container">
            <a class="navbar-brand bp-brand" href="{{ route('produits.index') }}">
                <span class="bp-brand-mark"><i class="bi bi-shop"></i></span>
                <span>Boutik <strong>Plus</strong></span>
            </a>
            <button class="navbar-toggler bp-nav-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#boutikNavigation" aria-controls="boutikNavigation" aria-expanded="false" aria-label="Ouvrir le menu">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="boutikNavigation">
                <div class="navbar-nav bp-nav-links me-auto">
                    @if (auth()->user()->estPatron())
                        <a class="nav-link {{ request()->routeIs('dashboard.*') ? 'active' : '' }}" href="{{ route('dashboard.index') }}">
                            <i class="bi bi-grid-1x2"></i><span>Tableau de bord</span>
                        </a>
                    @endif
                    <a class="nav-link {{ request()->routeIs('produits.*') ? 'active' : '' }}" href="{{ route('produits.index') }}">
                        <i class="bi bi-box-seam"></i><span>Produits</span>
                    </a>
                    <a class="nav-link {{ request()->routeIs('factures.*') ? 'active' : '' }}" href="{{ route('factures.index') }}">
                        <i class="bi bi-receipt"></i><span>Factures</span>
                    </a>
                    @if (auth()->user()->estPatron())
                        <a class="nav-link {{ request()->routeIs('caisse.*') ? 'active' : '' }}" href="{{ route('caisse.index') }}">
                            <i class="bi bi-cash-stack"></i><span>Caisse</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('fournisseurs.*') ? 'active' : '' }}" href="{{ route('fournisseurs.index') }}">
                            <i class="bi bi-truck"></i><span>Fournisseurs</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('achats.*') ? 'active' : '' }}" href="{{ route('achats.index') }}">
                            <i class="bi bi-bag"></i><span>Achats</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('utilisateurs.*') ? 'active' : '' }}" href="{{ route('utilisateurs.index') }}">
                            <i class="bi bi-people"></i><span>Équipe</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('boutique-settings.*') ? 'active' : '' }}" href="{{ route('boutique-settings.edit') }}">
                            <i class="bi bi-shop"></i><span>Boutique</span>
                        </a>
                        <a class="nav-link {{ request()->routeIs('compte.*') ? 'active' : '' }}" href="{{ route('compte.edit') }}">
                            <i class="bi bi-person-gear"></i><span>Mon compte</span>
                        </a>
                    @endif
                </div>

                <div class="bp-account">
                    <button type="button" class="btn bp-install-button" data-pwa-install hidden>Installer l’application</button>
                    <span class="bp-account-avatar" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                    <span class="bp-account-name">
                        <strong>{{ auth()->user()->name }}</strong>
                        <small>{{ auth()->user()->estPatron() ? 'Patron' : 'Employé' }}</small>
                    </span>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn bp-logout" aria-label="Se déconnecter">
                            <i class="bi bi-box-arrow-right"></i><span>Déconnexion</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <main class="container-fluid bp-container bp-main">
        @if (session('success'))
            <div class="alert alert-success bp-alert" role="status"><i class="bi bi-check-circle-fill"></i>{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger bp-alert" role="alert">
                <i class="bi bi-exclamation-circle-fill"></i>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
    <footer class="bp-footer">
        <div class="container-fluid bp-container">Boutik Plus <span>·</span> Gestion simple, boutique sereine.</div>
    </footer>

    <div class="modal fade bp-confirm-modal" id="actionConfirmation" tabindex="-1" aria-labelledby="actionConfirmationTitle" aria-describedby="actionConfirmationMessage" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="bp-confirm-icon"><i class="bi bi-shield-check"></i></span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <h2 class="modal-title" id="actionConfirmationTitle">Confirmer cette action</h2>
                    <p id="actionConfirmationMessage" class="mb-0">Voulez-vous continuer ?</p>
                    <ul id="actionConfirmationDetails" class="bp-confirm-details" hidden></ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-success" id="confirmActionButton">Confirmer</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (() => {
            const modalElement = document.getElementById('actionConfirmation');
            const confirmationButton = document.getElementById('confirmActionButton');
            const confirmationModal = window.bootstrap?.Modal.getOrCreateInstance(modalElement);
            let pendingForm = null;
            let pendingLink = null;

            function populateConfirmation(title, message, label, tone = 'success') {
                document.getElementById('actionConfirmationTitle').textContent = title;
                document.getElementById('actionConfirmationMessage').textContent = message;
                confirmationButton.textContent = label;
                confirmationButton.classList.toggle('btn-danger', tone === 'danger');
                confirmationButton.classList.toggle('btn-success', tone !== 'danger');

                const details = document.getElementById('actionConfirmationDetails');
                details.replaceChildren();

                return details;
            }

            document.addEventListener('submit', (event) => {
                const form = event.target;

                if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) {
                    return;
                }

                if (form.dataset.confirmed === 'true') {
                    delete form.dataset.confirmed;

                    return;
                }

                event.preventDefault();

                if (!confirmationModal) {
                    if (window.confirm(form.dataset.confirmMessage || 'Voulez-vous confirmer cette action ?')) {
                        form.dataset.confirmed = 'true';
                        form.requestSubmit(event.submitter instanceof HTMLElement ? event.submitter : undefined);
                    }

                    return;
                }

                pendingForm = form;
                const details = populateConfirmation(
                    form.dataset.confirmTitle || 'Confirmer cette action',
                    form.dataset.confirmMessage || 'Voulez-vous continuer ?',
                    form.dataset.confirmLabel || 'Confirmer',
                    form.dataset.confirmTone || 'success',
                );

                form.querySelectorAll('[data-confirm-summary]').forEach((field) => {
                    const value = field instanceof HTMLSelectElement
                        ? field.selectedOptions[0]?.textContent.trim()
                        : field.value.trim();

                    if (!value) {
                        return;
                    }

                    const detail = document.createElement('li');
                    detail.textContent = `${field.dataset.confirmSummary} : ${value}`;
                    details.append(detail);
                });

                details.hidden = details.childElementCount === 0;
                confirmationModal.show();
            });

            document.addEventListener('click', (event) => {
                const link = event.target.closest('a[data-confirm-link]');

                if (!link) {
                    return;
                }

                event.preventDefault();

                if (!confirmationModal) {
                    if (window.confirm(link.dataset.confirmMessage || 'Voulez-vous continuer ?')) {
                        window.open(link.href, '_blank', 'noopener');
                    }

                    return;
                }

                pendingLink = link;
                populateConfirmation(
                    link.dataset.confirmTitle || 'Confirmer cette action',
                    link.dataset.confirmMessage || 'Voulez-vous continuer ?',
                    link.dataset.confirmLabel || 'Continuer',
                    link.dataset.confirmTone || 'success',
                );
                confirmationModal.show();
            });

            confirmationButton.addEventListener('click', () => {
                if (pendingLink) {
                    const link = pendingLink;
                    pendingLink = null;
                    confirmationModal.hide();
                    window.open(link.href, '_blank', 'noopener');

                    return;
                }

                if (!pendingForm) {
                    return;
                }

                const form = pendingForm;
                pendingForm = null;
                form.dataset.confirmed = 'true';
                confirmationModal.hide();
                form.requestSubmit();
            });

            modalElement.addEventListener('hidden.bs.modal', () => {
                pendingForm = null;
                pendingLink = null;
            });
        })();

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('{{ asset('service-worker.js') }}');
        }
    </script>
</body>
</html>
