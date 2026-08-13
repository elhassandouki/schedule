<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeUsers(): array
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('secret'), 'role' => 'super_admin']);
        $sous = User::create(['name' => 'Sous', 'email' => 'sous@example.com', 'password' => bcrypt('secret'), 'role' => 'sous_admin']);
        $prof = User::create(['name' => 'Prof', 'email' => 'prof@example.com', 'password' => bcrypt('secret'), 'role' => 'prof']);
        return compact('admin', 'sous', 'prof');
    }

    public function test_admin_can_view_roles_page(): void
    {
        $users = $this->makeUsers();
        $this->actingAs($users['admin']);
        $this->get(route('roles.index'))->assertOk();
    }

    public function test_sous_admin_and_prof_cannot_view_roles_page(): void
    {
        $users = $this->makeUsers();

        $this->actingAs($users['sous']);
        $this->get(route('roles.index'))->assertForbidden();

        $this->actingAs($users['prof']);
        $this->get(route('roles.index'))->assertForbidden();
    }

    public function test_admin_can_edit_role_permissions(): void
    {
        $users = $this->makeUsers();
        $role = Role::findByName('prof');
        $this->actingAs($users['admin']);
        $this->get(route('roles.edit', $role))->assertOk();
    }

    public function test_admin_can_update_role_permissions(): void
    {
        $users = $this->makeUsers();
        $role = Role::findByName('prof');
        $this->actingAs($users['admin']);
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class]);

        $selected = ['view modules', 'view salles', 'export timetable'];
        $response = $this->put(route('roles.update', $role), ['permissions' => $selected]);
        $response->assertRedirect(route('roles.edit', $role));

        $this->assertTrue($role->fresh()->hasPermissionTo('view modules'));
        $this->assertFalse($role->fresh()->hasPermissionTo('view departements'));
    }

    public function test_super_admin_role_cannot_be_modified(): void
    {
        $users = $this->makeUsers();
        $this->actingAs($users['admin']);
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class]);

        $role = Role::findByName('super_admin');
        $response = $this->put(route('roles.update', $role), ['permissions' => []]);
        $response->assertRedirect(route('roles.index'));
    }

    public function test_super_admin_always_has_all_permissions(): void
    {
        $users = $this->makeUsers();
        $role = Role::findByName('super_admin');
        $all = Permission::count();
        $this->assertTrue($role->permissions->count() >= $all);
    }

    public function test_roles_page_shows_correct_assignments_count(): void
    {
        $users = $this->makeUsers();
        $this->actingAs($users['admin']);

        $response = $this->get(route('roles.index'));
        // La vue doit lister chaque rôle avec au moins ses permissions
        $profRole = Role::findByName('prof');
        $response->assertSee($profRole->permissions->count() . ' / ' . Permission::count(), false);
    }
}
