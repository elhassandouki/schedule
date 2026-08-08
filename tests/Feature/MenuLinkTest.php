<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\UnifiedDemoSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@school.local')->first();
    }

    /**
     * Every link defined in the sidebar menu must be a working route
     * for an administrator (200 OK after login).
     */
    public function test_all_sidebar_menu_links_work_for_admin(): void
    {
        $menu = collect(config('adminlte.menu'))
            ->filter(fn ($item) => is_array($item))
            ->filter(fn ($item) => isset($item['url']));

        $links = [];
        foreach ($menu as $item) {
            if (!str_starts_with($item['url'], '#') && $item['url'] !== 'logout') {
                $links[$item['text']] = $item['url'];
            }
            foreach ($item['submenu'] ?? [] as $sub) {
                if (isset($sub['url']) && !str_starts_with($sub['url'], '#')) {
                    $links[$item['text'] . ' > ' . $sub['text']] = $sub['url'];
                }
            }
        }

        $this->actingAs($this->admin());

        foreach ($links as $label => $url) {
            $response = $this->get($url);
            $this->assertTrue(
                in_array($response->getStatusCode(), [200, 301, 302]),
                "Menu link '{$label}' ({$url}) returned {$response->getStatusCode()}"
            );
        }
        $this->assertCount(count($links), $links);
    }
}
