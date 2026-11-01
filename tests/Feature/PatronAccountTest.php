<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ConfirmAccountEmail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class PatronAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_patron_can_update_profile_and_must_confirm_a_new_email(): void
    {
        Notification::fake();
        $patron = User::factory()->create([
            'name' => 'Patron actuel',
            'email' => 'patron@example.test',
            'role' => 'patron',
            'password' => 'ancien-mot-de-passe',
        ]);

        $this->actingAs($patron)
            ->patch(route('compte.update'), [
                'name' => 'Patron modifié',
                'email' => 'nouveau@example.test',
                'current_password' => 'ancien-mot-de-passe',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $patron->id,
            'name' => 'Patron modifié',
            'email' => 'patron@example.test',
        ]);
        Notification::assertSentOnDemand(ConfirmAccountEmail::class, function (ConfirmAccountEmail $notification, array $channels, object $notifiable): bool {
            return $notifiable->routeNotificationFor('mail') === 'nouveau@example.test'
                && $channels === ['mail'];
        });

        $pendingChange = Cache::get('account-email-change:'.$patron->id);
        $url = URL::temporarySignedRoute(
            'compte.email.confirm',
            now()->addMinutes(20),
            ['user' => $patron->id, 'nonce' => $pendingChange['nonce']],
        );

        $this->get($url)
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', [
            'id' => $patron->id,
            'email' => 'nouveau@example.test',
        ]);
    }

    public function test_password_change_requires_current_password_and_rotates_password(): void
    {
        $patron = User::factory()->create([
            'role' => 'patron',
            'password' => 'ancien-mot-de-passe',
        ]);

        $this->actingAs($patron)
            ->put(route('compte.password.update'), [
                'current_password' => 'incorrect',
                'password' => 'nouveau-mot-de-passe-solide',
                'password_confirmation' => 'nouveau-mot-de-passe-solide',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('ancien-mot-de-passe', $patron->fresh()->password));

        $this->actingAs($patron)
            ->put(route('compte.password.update'), [
                'current_password' => 'ancien-mot-de-passe',
                'password' => 'nouveau-mot-de-passe-solide',
                'password_confirmation' => 'nouveau-mot-de-passe-solide',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('nouveau-mot-de-passe-solide', $patron->fresh()->password));
    }

    public function test_employee_cannot_access_patron_account_settings(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);

        $this->actingAs($employee)
            ->get(route('compte.edit'))
            ->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected_from_patron_account_settings(): void
    {
        $this->get(route('compte.edit'))
            ->assertRedirect(route('login'));
    }

    public function test_patron_and_guest_can_render_account_and_recovery_forms(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $patron->update(['email' => 'patron@example.test']);

        $this->actingAs($patron)
            ->get(route('compte.edit'))
            ->assertOk()
            ->assertSee('Informations personnelles')
            ->assertSee('Changer le mot de passe')
            ->assertSee(route('compte.password.update'));

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Mot de passe oublié');

        $this->get(route('password.reset', [
            'token' => 'reset-token-demo',
            'email' => $patron->email,
        ]))
            ->assertOk()
            ->assertSee('Choisir un nouveau mot de passe')
            ->assertSee('patron@example.test');
    }

    public function test_password_recovery_sends_a_reset_link_without_disclosing_unknown_email(): void
    {
        Notification::fake();
        $patron = User::factory()->create([
            'role' => 'patron',
            'email' => 'patron@example.test',
        ]);

        $this->post(route('password.email'), ['email' => $patron->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($patron, ResetPassword::class);

        $this->post(route('password.email'), ['email' => 'inconnu@example.test'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Si un compte correspond à cette adresse, un lien de réinitialisation vient de lui être envoyé.');
    }

    public function test_valid_password_reset_link_changes_the_password_and_is_single_use(): void
    {
        $patron = User::factory()->create([
            'role' => 'patron',
            'email' => 'patron@example.test',
            'password' => 'ancien-mot-de-passe',
        ]);
        $token = Password::createToken($patron);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $patron->email,
            'password' => 'nouveau-mot-de-passe-solide',
            'password_confirmation' => 'nouveau-mot-de-passe-solide',
        ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('nouveau-mot-de-passe-solide', $patron->fresh()->password));

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $patron->email,
            'password' => 'autre-mot-de-passe-solide',
            'password_confirmation' => 'autre-mot-de-passe-solide',
        ])->assertSessionHasErrors('email');
    }

    public function test_expired_email_confirmation_link_cannot_change_account_email(): void
    {
        $patron = User::factory()->create([
            'email' => 'patron@example.test',
            'role' => 'patron',
        ]);
        $nonce = Str::random(64);
        Cache::put('account-email-change:'.$patron->id, [
            'email' => 'nouveau@example.test',
            'nonce' => $nonce,
        ], now()->addMinutes(30));
        $url = URL::temporarySignedRoute(
            'compte.email.confirm',
            now()->subMinute(),
            ['user' => $patron->id, 'nonce' => $nonce],
        );

        $this->get($url)->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $patron->id,
            'email' => 'patron@example.test',
        ]);
    }
}
