<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Boutik Plus - Gestion de stock et facturation pour petites boutiques au Benin</title>
    <meta name="description" content="Boutik Plus est l'outil simple de gestion de stock, facturation et caisse pour petites boutiques et PME au Benin. Fini les factures manuscrites et les erreurs de stock.">
    <meta name="keywords" content="gestion de stock Benin, facturation boutique Benin, logiciel caisse Cotonou, gestion boutique PME Benin, Boutik Plus">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ url('/') }}">

    <!-- Open Graph : pour un bel apercu quand le lien est partage sur WhatsApp / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Boutik Plus - Gestion de stock et facturation pour petites boutiques au Benin">
    <meta property="og:description" content="Fini les factures manuscrites et les erreurs de stock. Boutik Plus t'aide a gerer ta boutique simplement, au Benin.">
    <meta property="og:image" content="{{ asset('icons/icon-512.png') }}">
    <meta property="og:locale" content="fr_FR">

    @include('partials.pwa-head')

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/boutik-plus.css') }}" rel="stylesheet">

    <!-- Donnees structurees pour aider Google a comprendre ce qu'est Boutik Plus -->
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "SoftwareApplication",
        "name": "Boutik Plus",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "Web",
        "description": "Outil de gestion de stock, facturation et caisse pour petites boutiques et PME au Benin.",
        "offers": {
            "@@type": "Offer",
            "priceCurrency": "XOF",
            "price": "0"
        }
    }
    </script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="{{ url('/') }}">Boutik Plus</a>
            <a href="{{ route('login') }}" class="btn btn-outline-light btn-sm">Se connecter</a>
        </div>
    </nav>

    <header class="container text-center mt-5 mb-5">
        <h1 class="display-6">La gestion de ta boutique, simplifiee</h1>
        <p class="lead">Stock, factures et caisse au meme endroit. Fini les cahiers et les erreurs de calcul.</p>
        <a href="{{ route('login') }}" class="btn btn-success btn-lg mt-3">Commencer maintenant</a>
        <button type="button" class="btn btn-outline-success btn-lg mt-3" data-pwa-install>Installer Boutik Plus</button>
    </header>

    <section class="container mb-5">
        <div class="row gy-4 text-center">
            <div class="col-md-4">
                <div class="card p-4 h-100">
                    <h2 class="h5">Gestion du stock</h2>
                    <p class="mb-0">Suis tes produits en temps reel et recois une alerte des qu'un article se fait rare.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 h-100">
                    <h2 class="h5">Facturation simple</h2>
                    <p class="mb-0">Cree une facture en quelques clics, le stock se met a jour tout seul.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 h-100">
                    <h2 class="h5">Suivi de caisse</h2>
                    <p class="mb-0">Sais exactement combien il y a en caisse chaque jour, sans recompter a la main.</p>
                </div>
            </div>
        </div>
    </section>

    <footer class="container text-center text-muted mb-4">
        <p>Boutik Plus - fait pour les petites boutiques et PME du Benin.</p>
    </footer>
</body>
</html>
