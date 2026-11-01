<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaInstallationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_has_an_installable_entry_point_and_supported_icons(): void
    {
        $manifest = json_decode(
            file_get_contents(public_path('manifest.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame('Boutik Plus', $manifest['name']);
        $this->assertSame('/connexion', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame(['Produits', 'Factures'], array_column($manifest['shortcuts'], 'name'));
        $this->assertSame(['192x192', '512x512'], array_column($manifest['icons'], 'sizes'));
    }

    public function test_login_and_public_pages_include_installation_controls_and_service_worker(): void
    {
        $this->get(route('login'))
            ->assertSee('rel="manifest"', false)
            ->assertSee('data-pwa-install', false)
            ->assertSee('js/pwa.js')
            ->assertSee('service-worker.js');

        $this->get(route('vitrine'))
            ->assertSee('rel="manifest"', false)
            ->assertSee('data-pwa-install', false)
            ->assertSee('js/pwa.js');
    }

    public function test_authenticated_app_shell_offers_installation_control(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);

        $this->actingAs($employee)
            ->get(route('produits.index'))
            ->assertSee('rel="manifest"', false)
            ->assertSee('data-pwa-install', false)
            ->assertSee('js/pwa.js');
    }

    public function test_logged_in_employee_opening_pwa_entry_is_sent_to_products(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);

        $this->actingAs($employee)
            ->get(route('login'))
            ->assertRedirect(route('produits.index'));
    }

    public function test_offline_shell_exists_without_cached_business_data(): void
    {
        $offlinePage = file_get_contents(public_path('offline.html'));
        $serviceWorker = file_get_contents(public_path('service-worker.js'));

        $this->assertStringContainsString('Aucune donnée de gestion n’est enregistrée', $offlinePage);
        $this->assertStringContainsString("event.request.mode === 'navigate'", $serviceWorker);
        $this->assertStringContainsString("caches.match('/offline.html')", $serviceWorker);
        $this->assertStringContainsString('const STATIC_URLS = new Set(PRECACHE_URLS);', $serviceWorker);
        $this->assertStringNotContainsString("'/produits'", $serviceWorker);
        $this->assertStringNotContainsString("'/factures'", $serviceWorker);
    }
}
