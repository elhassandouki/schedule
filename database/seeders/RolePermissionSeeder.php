<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seed les rôles et permissions avec spatie/laravel-permission.
 *
 * Rôles : super_admin, sous_admin, chef_departement, chef_filiere, prof
 *
 * Logique de granularité :
 *  - super_admin        : toutes les permissions, y compris la gestion des utilisateurs et des rôles.
 *  - sous_admin         : tout sauf la gestion des rôles/permissions système.
 *  - chef_departement   : gère filières, semestres, modules, groupes, professeurs, sessions et générations.
 *  - chef_filiere       : gère semestres, modules, groupes, professeurs, sessions et générations.
 *  - prof               : consultation uniquement (emplois du temps, exports).
 */
class RolePermissionSeeder extends Seeder
{
    /** @var string[] Ressources CRUD du système */
    private array $crudResources = [
        'departements',
        'filieres',
        'semestres',
        'salles',
        'modules',
        'groupes',
        'professeurs',
    ];

    /** @var string[] Ressources d'emploi du temps */
    private array $timetableResources = ['sessions', 'generations'];

    public function run(): void
    {
        // Reset cache des permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ---------- Permissions ----------
        $crudPermissions = [];
        foreach ($this->crudResources as $resource) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $crudPermissions[] = "{$action} {$resource}";
            }
        }

        $timetablePermissions = [];
        foreach ($this->timetableResources as $resource) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $timetablePermissions[] = "{$action} {$resource}";
            }
        }

        $allPermissions = array_merge(
            $crudPermissions,
            $timetablePermissions,
            [
                'manage users',
                'manage roles',
                'export timetable',
                'view quality reports',
            ]
        );

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ---------- Rôles ----------
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $sousAdmin = Role::firstOrCreate(['name' => 'sous_admin', 'guard_name' => 'web']);
        $chefDep = Role::firstOrCreate(['name' => 'chef_departement', 'guard_name' => 'web']);
        $chefFil = Role::firstOrCreate(['name' => 'chef_filiere', 'guard_name' => 'web']);
        $prof = Role::firstOrCreate(['name' => 'prof', 'guard_name' => 'web']);

        // super_admin : tout
        $superAdmin->syncPermissions($allPermissions);

        // sous_admin : tout sauf la gestion des rôles système
        $sousAdmin->syncPermissions(
            collect($allPermissions)->reject(fn (string $p) => str_starts_with($p, 'manage roles'))->all()
        );

        // chef_departement : CRUD références + emploi du temps + utilisateurs (lecture), sans manage roles
        $chefDep->syncPermissions(
            collect($allPermissions)
                ->reject(fn (string $p) => in_array($p, ['manage roles', 'create departements', 'delete departements'], true))
                ->all()
        );

        // chef_filiere : semestres, modules, groupes, professeurs, sessions, générations + consultations
        $chefFilPermissions = collect($allPermissions)
            ->reject(fn (string $p) => in_array(
                $p,
                array_merge(
                    ['manage roles', 'manage users'],
                    ['create departements', 'update departements', 'delete departements', 'view departements'],
                    ['create filieres', 'update filieres', 'delete filieres']
                ),
                true
            ))
            ->all();
        $chefFil->syncPermissions($chefFilPermissions);

        // prof : consultation + exports + quality
        $prof->syncPermissions([
            'view sessions',
            'view generations',
            'export timetable',
            'view quality reports',
            'view departements',
            'view filieres',
            'view semestres',
            'view salles',
            'view modules',
            'view groupes',
            'view professeurs',
        ]);
    }
}
