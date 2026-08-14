<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUsers(): array
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('secret'), 'role' => 'super_admin']);
        $prof = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('secret'), 'role' => 'prof']);
        return compact('admin', 'prof');
    }

    public function test_admin_can_view_settings_page(): void
    {
        $users = $this->makeUsers();
        $this->actingAs($users['admin']);
        $this->get(route('settings.edit'))->assertOk();
    }

    public function test_prof_cannot_view_settings_page(): void
    {
        $users = $this->makeUsers();
        $this->actingAs($users['prof']);
        $this->get(route('settings.edit'))->assertForbidden();
    }

    public function test_admin_can_update_establishment_info(): void
    {
        $users = $this->makeUsers();
        $this->actingAs($users['admin']);
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class]);

        $response = $this->put(route('settings.update'), [
            'establishment_name' => 'Université de Test',
            'establishment_address' => 'Route de la gare',
            'establishment_phone' => '+213 48 00 00 00',
            'establishment_email' => 'contact@univ-test.dz',
        ]);

        $response->assertRedirect(route('settings.edit'));
        $this->assertEquals('Université de Test', Setting::get('establishment_name'));
        $this->assertEquals('contact@univ-test.dz', Setting::get('establishment_email'));
    }

    public function test_admin_can_upload_logo(): void
    {
        $users = $this->makeUsers();
        $this->actingAs($users['admin']);
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class]);

        Storage::fake('public');
        $file = UploadedFile::fake()->image('logo.png');

        $response = $this->put(route('settings.update'), [
            'establishment_name' => 'Université Logo',
            'establishment_address' => '',
            'establishment_phone' => '',
            'establishment_email' => '',
            'logo' => $file,
        ]);

        $response->assertRedirect(route('settings.edit'));

        // Le fichier est stocké avec son nom d'origine via ->store('logos', 'public')
        $storedPath = Setting::get('logo_path', '');
        Storage::disk('public')->assertExists($storedPath);
    }

    public function test_invalid_email_rejected(): void
    {
        $users = $this->makeUsers();
        $this->actingAs($users['admin']);
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class]);

        $this->put(route('settings.update'), [
            'establishment_name' => 'Université Test',
            'establishment_email' => 'pas-un-email',
        ])->assertSessionHasErrors('establishment_email');
    }

    public function test_default_values_after_migration(): void
    {
        $this->assertEquals('Université', Setting::get('establishment_name'));
    }
}
