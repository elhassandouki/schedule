<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrudRouteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\UnifiedDemoSeeder::class);
    }

     
    public function test_dashboard_renders_with_wizard(): void
    {
        $this->actingAs($this->admin());
        $response = $this->get(route('dashboard'));
        $response->assertOk();
        $response->assertSee('Configuration guidée');
        // After seed, all reference data exists so wizard should be ready
        $response->assertSee('Données prêtes');
    }

    private function admin(): User
    {
        return User::where('email', 'admin@school.local')->first();
    }

    
    public function test_all_crud_resources_respond_ok_for_admin(): void
    {
        $resources = ['annees','departements','filieres','semestres','modules','salles',
                      'professeurs','affectations-modules','teachers','subjects',
                      'sections','students','timeslots','days'];
        $this->actingAs($this->admin());
        foreach ($resources as $res) {
            $response = $this->get(route('crud.index', $res));
            $response->assertOk();
        }
        $this->assertTrue(true);
    }

    
    public function test_unknown_resource_returns_404(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('crud.index', 'inconnu'))->assertNotFound();
    }

    
    public function test_prof_cannot_access_crud(): void
    {
        $prof = User::where('email', 'alice@school.local')->first();
        $this->actingAs($prof);
        $this->get(route('crud.index', 'departements'))->assertStatus(403);
    }
}

