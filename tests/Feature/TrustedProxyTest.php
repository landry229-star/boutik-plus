<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_https_forwarded_by_render_is_used_for_generated_urls(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '10.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'boutik-plus.onrender.com',
        ])
            ->get('/connexion')
            ->assertSee('https://boutik-plus.onrender.com/mot-de-passe-oublie', false);
    }
}
