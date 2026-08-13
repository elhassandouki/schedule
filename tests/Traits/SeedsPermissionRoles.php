<?php

namespace Tests\Traits;

use Database\Seeders\RolePermissionSeeder;

/**
 * Ensures spatie/laravel-permission roles & permissions exist in the
 * (RefreshDatabase-refreshed) test database before each test that needs them.
 */
trait SeedsPermissionRoles
{
    protected function seedPermissionRoles(): void
    {
        $this->artisan('db:seed', ['--class' => RolePermissionSeeder::class, '--no-interaction' => true]);
    }
}
