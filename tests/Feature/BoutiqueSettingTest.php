<?php

namespace Tests\Feature;

use App\Models\BoutiqueSetting;
use App\Models\User;
use App\Services\PdfBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BoutiqueSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_patron_can_update_shop_identity_and_upload_a_logo(): void
    {
        Storage::fake('public');
        $patron = User::factory()->create(['role' => 'patron']);

        $this->actingAs($patron)
            ->put(route('boutique-settings.update'), [
                'name' => 'Ma Boutique',
                'address' => '12 rue du Marché, Cotonou',
                'phone' => '+229 97 00 00 00',
                'email' => 'contact@boutique.example',
                'ifu' => '3200000000000',
                'rccm' => 'RB/COT/20 B 12345',
                'legal_notice' => 'Merci de votre confiance.',
                'logo' => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertRedirect(route('boutique-settings.edit'))
            ->assertSessionHas('success');

        $settings = BoutiqueSetting::query()->sole();

        $this->assertDatabaseHas('boutique_settings', [
            'id' => $settings->id,
            'key' => 'boutique',
            'name' => 'Ma Boutique',
            'address' => '12 rue du Marché, Cotonou',
            'phone' => '+229 97 00 00 00',
            'email' => 'contact@boutique.example',
            'ifu' => '3200000000000',
            'rccm' => 'RB/COT/20 B 12345',
            'legal_notice' => 'Merci de votre confiance.',
        ]);
        Storage::disk('public')->assertExists($settings->logo_path);

        $branding = app(PdfBranding::class)->viewData();

        $this->assertSame('Ma Boutique', $branding['company']['name']);
        $this->assertSame('3200000000000', $branding['company']['ifu']);
        $this->assertSame('RB/COT/20 B 12345', $branding['company']['rccm']);
        $this->assertStringStartsWith('data:image/png;base64,', $branding['logoDataUri']);
    }

    public function test_employee_cannot_view_or_update_shop_identity(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);

        $this->actingAs($employee)
            ->get(route('boutique-settings.edit'))
            ->assertForbidden();

        $this->actingAs($employee)
            ->put(route('boutique-settings.update'), ['name' => 'Nom interdit'])
            ->assertForbidden();

        $this->assertDatabaseCount('boutique_settings', 0);
    }

    public function test_invalid_shop_email_does_not_change_saved_identity(): void
    {
        $patron = User::factory()->create(['role' => 'patron']);
        $settings = BoutiqueSetting::current();

        $this->actingAs($patron)
            ->put(route('boutique-settings.update'), [
                'name' => 'Nom qui ne doit pas être enregistré',
                'email' => 'adresse invalide',
            ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseHas('boutique_settings', [
            'id' => $settings->id,
            'name' => 'Boutik Plus',
            'email' => null,
        ]);
    }

    public function test_non_image_logo_upload_does_not_change_saved_identity(): void
    {
        Storage::fake('public');
        $patron = User::factory()->create(['role' => 'patron']);
        $settings = BoutiqueSetting::current();

        $this->actingAs($patron)
            ->put(route('boutique-settings.update'), [
                'name' => 'Nom qui ne doit pas être enregistré',
                'logo' => UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml'),
            ])
            ->assertSessionHasErrors('logo');

        $this->assertDatabaseHas('boutique_settings', [
            'id' => $settings->id,
            'name' => 'Boutik Plus',
            'logo_path' => null,
        ]);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_patron_can_remove_the_current_logo(): void
    {
        Storage::fake('public');
        $patron = User::factory()->create(['role' => 'patron']);
        $settings = BoutiqueSetting::current();
        $settings->update(['logo_path' => UploadedFile::fake()->image('logo.png')->store('branding', 'public')]);
        $previousLogoPath = $settings->logo_path;

        $this->actingAs($patron)
            ->put(route('boutique-settings.update'), [
                'name' => $settings->name,
                'remove_logo' => '1',
            ])
            ->assertRedirect(route('boutique-settings.edit'));

        $this->assertDatabaseHas('boutique_settings', [
            'id' => $settings->id,
            'logo_path' => null,
        ]);
        Storage::disk('public')->assertMissing($previousLogoPath);
    }
}
