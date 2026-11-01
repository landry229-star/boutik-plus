# Boutik Plus

Application de gestion de stock, facturation et suivi de caisse pour petites boutiques au Benin.

## Fonctionnalites

- Gestion des produits et du stock, avec alerte automatique de stock faible
- Facturation avec deduction automatique du stock et generation de numero de facture
- Suivi de caisse quotidien, avec mise a jour automatique a chaque vente
- Authentification avec deux roles : patron et employe
- Gestion d'equipe, fournisseurs et achats
- Tableau de bord avec indicateurs de ventes
- Paiements en especes et Mobile Money distingues dans la caisse
- Mouvements de stock et de caisse relies aux utilisateurs et operations correspondantes
- Archivage des produits, fournisseurs et employes pour conserver l'historique
- Annulation tracee des factures avec remise en stock et ecriture compensatoire de caisse
- Rapport des ventes filtrable par periode et moyen de paiement
- Pagination des listes operationnelles

Les comptes employes peuvent consulter le stock et enregistrer des ventes, mais ne peuvent pas administrer les produits, le stock, la caisse, l'equipe, les fournisseurs ou les achats. Seul un compte patron actif peut annuler une facture.

## Installation

1. Installer les dependances PHP et JavaScript :

   ```sh
   composer install
   npm install
   ```

2. Configurer `.env`. SQLite est utilise par defaut. Creer `database/database.sqlite` si le fichier n'existe pas.
3. Executer les migrations et creer le premier compte patron :

   ```sh
   php artisan migrate --seed
   ```

   Par defaut, le compte utilise `patron@boutik.local`. Le seeder genere un mot de passe aleatoire et l'affiche uniquement lors de la creation. Pour personnaliser le nom ou l'adresse, definir `BOUTIK_PATRON_NAME` et `BOUTIK_PATRON_EMAIL` dans `.env`. Un mot de passe fixe peut etre fourni avec `BOUTIK_PATRON_PASSWORD`.

4. Demarrer le serveur :

   ```sh
   php artisan serve
   ```

5. Se connecter sur http://localhost:8000/connexion.

Pour compiler les ressources frontend, executer `npm run build`.

## Structure

- `app/Models` : modeles User, Produit, Facture, Achat et mouvements
- `app/Http/Controllers` : logique HTTP des modules metier
- `app/Http/Middleware/EstPatron.php` : restriction des routes au patron
- `database/migrations` : schema de la base
- `database/seeders/PatronSeeder.php` : creation securisee du premier compte patron
- `resources/views` : vues Blade
- `routes/web.php` : routes publiques et authentifiees
