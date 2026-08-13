<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Le hook afterRefreshingDatabase du trait RefreshDatabase est résolu au
     * niveau de la classe qui `use` le trait. Comme chaque classe de test
     * importe RefreshDatabase, le hook hérité de TestCase est masqué.
     *
     * On redéfinit donc la logique via setUp() en détectant si les tables
     * spatie existent déjà (elles sont créées par RefreshDatabase lors de la
     * migration), et on reseede si nécessaire à la fin de setUp.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase exécute les migrations APRÈS parent::setUp(), dans
        // setUpTraits(). Le code ci-dessous est donc exécuté au prochain
        // cycle. Pour garantir que les rôles existent à chaque test, on
        // reseed ici : si les tables spatie existent mais sont vides (cas
        // après RefreshDatabase), le seeder les remplit.
        if (\Illuminate\Support\Facades\Schema::hasTable('roles')) {
            if (!\Spatie\Permission\Models\Role::exists()) {
                (new \Database\Seeders\RolePermissionSeeder())->run();
            }
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }
    }
}
