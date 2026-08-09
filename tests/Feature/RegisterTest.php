<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_is_public(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Créer un nouveau compte');
    }

    public function test_login_page_links_to_register(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('register'), false);
    }

    public function test_visitor_can_register_and_is_logged_in(): void
    {
        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ])->post(route('register.store'), [
            'name' => 'Nouveau Prof',
            'email' => 'nouveau@school.local',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('generation');

        $this->assertDatabaseHas('users', [
            'email' => 'nouveau@school.local',
            'name' => 'Nouveau Prof',
            'role' => 'prof',
        ]);

        $this->assertAuthenticatedAs(User::where('email', 'nouveau@school.local')->first());
    }

    public function test_registration_rejects_weak_password(): void
    {
        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ])->post(route('register.store'), [
            'name' => 'Bad User',
            'email' => 'bad@school.local',
            'password' => '123',
            'password_confirmation' => '123',
        ])
            ->assertSessionHasErrors('password')
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['email' => 'bad@school.local']);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::create([
            'name' => 'Existing',
            'email' => 'taken@school.local',
            'password' => bcrypt('secret1234'),
            'role' => 'prof',
        ]);

        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ])->post(route('register.store'), [
            'name' => 'Another',
            'email' => 'taken@school.local',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ])
            ->assertSessionHasErrors('email')
            ->assertRedirect();
    }
}
