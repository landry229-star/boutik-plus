<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_sixth_login_attempt_for_the_same_email_and_ip_is_throttled(): void
    {
        $email = 'login-limit@example.test';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => $email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), [
            'email' => $email,
            'password' => 'wrong-password',
        ])->assertTooManyRequests();

        $this->assertGuest();
    }

    public function test_login_rate_limit_is_scoped_to_the_email_and_client_ip_pair(): void
    {
        $user = User::factory()->create([
            'email' => 'login-ip@example.test',
            'password' => 'correct-password',
            'role' => 'patron',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
                ->post(route('login.store'), [
                    'email' => mb_strtoupper($user->email),
                    'password' => 'wrong-password',
                ])->assertSessionHasErrors('email');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'correct-password',
            ])
            ->assertRedirect(route('dashboard.index'));

        $this->assertAuthenticatedAs($user);
    }
}
