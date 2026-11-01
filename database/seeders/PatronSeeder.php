<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class PatronSeeder extends Seeder
{
    /**
     * Crée le compte patron à partir des paramètres BOUTIK_PATRON_* du fichier .env.
     *
     * Pour changer le nom, l'adresse e-mail ou le mot de passe, modifiez les lignes
     * BOUTIK_PATRON_NAME, BOUTIK_PATRON_EMAIL et BOUTIK_PATRON_PASSWORD dans .env.
     * Le fichier à modifier est à la racine du projet; ne mettez pas vos vrais secrets dans .env.example.
     *
     * Par défaut, le seeder ne remplace jamais un compte patron existant.
     * Si vous changez l'e-mail ou le mot de passe d'un compte qui existe déjà,
     * le nouveau réglage n'est pas appliqué automatiquement. Pour le remplacer,
     * ajoutez temporairement BOUTIK_PATRON_RECREATE_CONFIRM=RECREER_PATRON dans .env,
     * puis lancez la commande ci-dessous. Retirez ensuite cette confirmation du .env.
     * php artisan db:seed --class=PatronSeeder --force --no-interaction
     *
     * Lors d'un remplacement, les historiques commerciaux sont conservés et
     * leurs noms d'auteurs restent affichés grâce aux colonnes de snapshot.
     */
    public function run(): void
    {
        // Modifiez BOUTIK_PATRON_NAME, BOUTIK_PATRON_EMAIL et BOUTIK_PATRON_PASSWORD
        // dans le fichier .env à la racine du projet pour choisir les identifiants.
        // config/boutik.php fait le lien entre le fichier .env et ces réglages Laravel.
        $name = config('boutik.patron.name');
        $email = config('boutik.patron.email');
        $recreateConfirmation = config('boutik.patron.recreate_confirmation');

        // Refuser une configuration incomplète évite de créer un compte inaccessible ou mal identifié.
        if (! is_string($name) || trim($name) === '' || ! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Configurez BOUTIK_PATRON_NAME et une adresse BOUTIK_PATRON_EMAIL valide avant de lancer le seeder.');
        }

        // La suppression d'un compte est une opération de développement sensible; elle est toujours interdite en production.
        if ($recreateConfirmation === 'RECREER_PATRON' && app()->isProduction()) {
            throw new RuntimeException('Le remplacement destructif du compte patron est bloqué en production.');
        }

        // Le seeder ne doit jamais transformer un compte employé en patron en réutilisant son adresse e-mail.
        $configuredAccount = User::query()->where('email', $email)->first();

        if ($configuredAccount && ! $configuredAccount->estPatron()) {
            throw new RuntimeException("L'adresse {$email} existe déjà pour un compte qui n'est pas patron.");
        }

        // Cas normal : si le compte existe déjà, on s'arrête sans modifier son mot de passe.
        if ($configuredAccount && $recreateConfirmation !== 'RECREER_PATRON') {
            $this->command?->info("Le compte patron {$email} existe déjà. Pour le remplacer, définissez BOUTIK_PATRON_RECREATE_CONFIRM=RECREER_PATRON.");

            return;
        }

        // Un mot de passe vide dans .env déclenche la création d'un mot de passe aléatoire difficile à deviner.
        // Le seeder l'affichera une seule fois dans le terminal; il ne faut donc pas fermer cette sortie avant de le noter.
        $password = config('boutik.patron.password');
        $generatedPassword = ! is_string($password) || $password === '';
        $password = $generatedPassword ? Str::random(40) : $password;

        // Même pour un compte de développement, on impose une longueur minimale au mot de passe configuré.
        if (mb_strlen($password) < 8) {
            throw new RuntimeException('BOUTIK_PATRON_PASSWORD doit contenir au moins 8 caractères.');
        }

        // Toute la vérification et la recréation sont atomiques : une erreur annule l'opération au lieu de laisser la boutique sans patron.
        $user = DB::transaction(function () use ($email, $name, $password, $recreateConfirmation): ?User {
            // Le verrou protège contre deux exécutions simultanées du seeder sur le même compte.
            $existingUser = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($existingUser && ! $existingUser->estPatron()) {
                throw new RuntimeException("L'adresse {$email} existe déjà pour un compte qui n'est pas patron.");
            }

            if ($existingUser && $recreateConfirmation !== 'RECREER_PATRON') {
                $this->command?->info("Le compte patron {$email} existe déjà. Pour le remplacer, définissez BOUTIK_PATRON_RECREATE_CONFIRM=RECREER_PATRON.");

                return null;
            }

            // Un seul patron peut être remplacé automatiquement; plusieurs patrons nécessitent un choix manuel pour éviter une suppression ambiguë.
            $patrons = User::query()->where('role', 'patron')->lockForUpdate()->get();

            if ($recreateConfirmation === 'RECREER_PATRON' && $patrons->count() > 1) {
                throw new RuntimeException('Plusieurs comptes patron existent. Réduisez-les explicitement avant de lancer leur remplacement.');
            }

            if ($recreateConfirmation === 'RECREER_PATRON') {
                $oldPatron = $patrons->first();

                if ($oldPatron) {
                    // Les snapshots gardent le nom de l'ancien auteur; les clés étrangères peuvent donc devenir null sans effacer les ventes, achats ni mouvements.
                    // On supprime aussi les sessions et jetons de réinitialisation pour invalider l'ancien accès.
                    DB::table('sessions')->where('user_id', $oldPatron->id)->delete();
                    DB::table('password_reset_tokens')->where('email', $oldPatron->email)->delete();
                    // Les notifications personnelles ne sont pas transférées au nouveau compte patron.
                    DB::table('notifications')
                        ->where('notifiable_type', User::class)
                        ->where('notifiable_id', $oldPatron->id)
                        ->delete();
                    $oldPatron->delete();
                }
            }

            // Hash::make hache le mot de passe avant son enregistrement; il n'est jamais stocké en clair.
            return User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'patron',
                'actif' => true,
            ]);
        }, 3);

        // Une sortie null signifie que le compte existant a été gardé sans changement.
        if (! $user) {
            return;
        }

        $this->command?->info("Compte patron créé : {$user->name} ({$user->email})");

        if ($generatedPassword) {
            $this->command?->warn("Mot de passe initial (à conserver maintenant, il ne sera pas réaffiché) : {$password}");
        } else {
            // Le mot de passe n'est jamais affiché lorsqu'il a été fourni manuellement dans .env.
            $this->command?->info('Le mot de passe utilisé est celui de BOUTIK_PATRON_PASSWORD.');
        }
    }
}
