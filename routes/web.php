<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AchatController;
use App\Http\Controllers\AchatPdfController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\BoutiqueSettingController;
use App\Http\Controllers\CaisseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FactureController;
use App\Http\Controllers\FacturePdfController;
use App\Http\Controllers\FournisseurController;
use App\Http\Controllers\MobileMoneyPaymentController;
use App\Http\Controllers\MobileMoneyWebhookController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\ProduitPdfController;
use App\Http\Controllers\UtilisateurController;
use App\Http\Controllers\VitrineController;
use Illuminate\Support\Facades\Route;

// Page publique de presentation (vitrine, visible sans connexion, pour le referencement)
Route::get('/', [VitrineController::class, 'index'])->name('vitrine');

// Connexion (accessible sans etre connecte)
Route::get('connexion', [LoginController::class, 'create'])->name('login');
Route::post('connexion', [LoginController::class, 'store'])
    ->middleware('throttle:login')
    ->name('login.store');
Route::post('deconnexion', [LoginController::class, 'destroy'])->name('logout');
Route::post('webhooks/fedapay', MobileMoneyWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.fedapay');
Route::match(['GET', 'POST'], 'mobile-money/fedapay/retour/{reference}', [MobileMoneyPaymentController::class, 'callback'])
    ->middleware('throttle:30,1')
    ->name('mobile-money.callback');
Route::get('mot-de-passe-oublie', [PasswordRecoveryController::class, 'requestForm'])->name('password.request');
Route::post('mot-de-passe-oublie', [PasswordRecoveryController::class, 'sendResetLink'])
    ->middleware('throttle:6,1')
    ->name('password.email');
Route::get('reinitialiser-mot-de-passe/{token}', [PasswordRecoveryController::class, 'resetForm'])->name('password.reset');
Route::post('reinitialiser-mot-de-passe', [PasswordRecoveryController::class, 'reset'])
    ->middleware('throttle:6,1')
    ->name('password.update');
Route::get('compte/{user}/confirmer-email', [AccountController::class, 'confirmEmail'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('compte.email.confirm');

// Tout le reste de l'application exige d'etre connecte
Route::middleware(['auth', 'compte.actif', 'auth.session'])->group(function () {

    // Le stock est consultable par toute l'equipe.
    Route::get('produits', [ProduitController::class, 'index'])->name('produits.index');

    // Factures : consultable par patron et employe
    Route::get('factures', [FactureController::class, 'index'])->name('factures.index');
    Route::get('factures/creer', [FactureController::class, 'create'])->name('factures.create');
    Route::post('factures', [FactureController::class, 'store'])->name('factures.store');
    Route::post('factures/{facture}/paiement/verifier', [MobileMoneyPaymentController::class, 'refresh'])
        ->middleware('throttle:20,1')
        ->name('factures.paiement.verifier');
    Route::post('factures/{facture}/paiement/reprendre', [MobileMoneyPaymentController::class, 'resume'])
        ->middleware('throttle:10,1')
        ->name('factures.paiement.reprendre');
    Route::get('factures/{facture}/pdf', [FacturePdfController::class, 'download'])->name('factures.pdf');
    Route::get('factures/{facture}', [FactureController::class, 'show'])->name('factures.show');
    Route::post('factures/{facture}/envoyer-email', [FactureController::class, 'envoyerEmail'])->name('factures.envoyer-email');

    // Les actions de gestion et d'ajustement du stock sont reservees au patron.
    Route::middleware('est.patron')->group(function () {
        Route::get('mon-compte', [AccountController::class, 'edit'])->name('compte.edit');
        Route::patch('mon-compte', [AccountController::class, 'update'])
            ->middleware('throttle:6,1')
            ->name('compte.update');
        Route::put('mon-compte/mot-de-passe', [AccountController::class, 'updatePassword'])
            ->middleware('throttle:6,1')
            ->name('compte.password.update');

        Route::resource('produits', ProduitController::class)->except(['index', 'show']);
        Route::get('produits/{produit}/pdf', [ProduitPdfController::class, 'download'])->name('produits.pdf');
        Route::delete('produits/{produit}/suppression-definitive', [ProduitController::class, 'supprimerDefinitivement'])->name('produits.supprimer-definitivement');
        Route::post('produits/{produit}/mouvement', [ProduitController::class, 'mouvement'])->name('produits.mouvement');
        Route::patch('produits/{produit}/activer', [ProduitController::class, 'activer'])->name('produits.activer');
        Route::post('factures/{facture}/annuler', [FactureController::class, 'annuler'])->name('factures.annuler');
        Route::delete('factures/{facture}/suppression-definitive', [FactureController::class, 'supprimerDefinitivement'])->name('factures.supprimer-definitivement');

        Route::get('tableau-de-bord', [DashboardController::class, 'index'])->name('dashboard.index');
        Route::get('boutique/informations', [BoutiqueSettingController::class, 'edit'])->name('boutique-settings.edit');
        Route::put('boutique/informations', [BoutiqueSettingController::class, 'update'])->name('boutique-settings.update');
        Route::get('caisse', [CaisseController::class, 'index'])->name('caisse.index');
        Route::post('caisse', [CaisseController::class, 'store'])->name('caisse.store');
        Route::post('caisse/{mouvement}/annuler', [CaisseController::class, 'annuler'])->name('caisse.annuler');
        Route::delete('caisse/{mouvement}/suppression-definitive', [CaisseController::class, 'supprimerDefinitivement'])->name('caisse.supprimer-definitivement');

        Route::get('equipe', [UtilisateurController::class, 'index'])->name('utilisateurs.index');
        Route::get('equipe/ajouter', [UtilisateurController::class, 'create'])->name('utilisateurs.create');
        Route::get('equipe/{utilisateur}/modifier', [UtilisateurController::class, 'edit'])->name('utilisateurs.edit');
        Route::post('equipe', [UtilisateurController::class, 'store'])->name('utilisateurs.store');
        Route::patch('equipe/{utilisateur}', [UtilisateurController::class, 'update'])->name('utilisateurs.update');
        Route::delete('equipe/{utilisateur}', [UtilisateurController::class, 'destroy'])->name('utilisateurs.destroy');
        Route::patch('equipe/{utilisateur}/statut', [UtilisateurController::class, 'statut'])->name('utilisateurs.statut');
        Route::delete('equipe/{utilisateur}/suppression-definitive', [UtilisateurController::class, 'supprimerDefinitivement'])->name('utilisateurs.supprimer-definitivement');

        // Fournisseurs et achats : reserve au patron
        Route::resource('fournisseurs', FournisseurController::class)->except('show');
        Route::delete('fournisseurs/{fournisseur}/suppression-definitive', [FournisseurController::class, 'supprimerDefinitivement'])->name('fournisseurs.supprimer-definitivement');
        Route::patch('fournisseurs/{fournisseur}/activer', [FournisseurController::class, 'activer'])->name('fournisseurs.activer');
        Route::get('achats', [AchatController::class, 'index'])->name('achats.index');
        Route::get('achats/creer', [AchatController::class, 'create'])->name('achats.create');
        Route::post('achats', [AchatController::class, 'store'])->name('achats.store');
        Route::get('achats/{achat}', [AchatController::class, 'show'])->name('achats.show');
        Route::get('achats/{achat}/pdf', [AchatPdfController::class, 'download'])->name('achats.pdf');
        Route::post('achats/{achat}/paiements', [AchatController::class, 'paiement'])->name('achats.paiement');
        Route::post('achats/{achat}/annuler', [AchatController::class, 'annuler'])->name('achats.annuler');
        Route::delete('achats/{achat}/suppression-definitive', [AchatController::class, 'supprimerDefinitivement'])->name('achats.supprimer-definitivement');
    });
});
