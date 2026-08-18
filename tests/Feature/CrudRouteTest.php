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
        if (!$response->isOk() && $response->exception) {
            fwrite(STDERR, "DASH ERR: " . $response->exception->getMessage() . " at " . $response->exception->getFile() . ":" . $response->exception->getLine() . "\n");
        }
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
                      'professeurs','affectations-modules','disponibilites-profs','conditions-groupes','groupes','timeslots','days'];
        $this->actingAs($this->admin());
        foreach ($resources as $res) {
            $response = $this->get(route('crud.index', $res));
            if (!$response->isOk()) {
                fwrite(STDERR, "FAIL: $res => {$response->getStatusCode()} " . str_replace(["\n","  "], "", substr(strip_tags($response->getContent()), strpos($response->getContent(), "exception") > 0 ? strpos($response->getContent(), "exception")-200 : 0, 800)) . "\n");
                $response->assertOk();
            }
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
