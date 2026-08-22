<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UnifiedDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EtatPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_etat_page_is_available(): void
    {
        $this->seed(UnifiedDemoSeeder::class);
        $this->actingAs(User::where('role', 'super_admin')->first());
        $this->get(route('etat.index'))
            ->assertOk()
            ->assertSee('État des emplois du temps')
            ->assertSee('PDF global')
            ->assertSee('Par filière et semestre');
    }
}

