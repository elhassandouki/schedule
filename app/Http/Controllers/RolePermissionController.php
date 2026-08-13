<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Gestion fine des rôles et de leurs permissions (spatie/laravel-permission).
 *
 * Accessible uniquement au rôle super_admin
 * (middleware role:super_admin dans web.php).
 */
class RolePermissionController extends Controller
{
    /**
     * Liste des rôles avec leurs permissions.
     */
    public function index(): View
    {
        $roles = Role::with('permissions')->orderBy('name')->get();
        $permissions = Permission::orderBy('name')->pluck('name')->all();
        $rolesCount = Role::count();
        $permissionsCount = Permission::count();
        $assignmentsCount = Permission::whereHas('roles')->count()
            ?: \DB::table(config('permission.table_names.role_has_permissions'))->count();

        return view('roles.index', compact('roles', 'permissions', 'rolesCount', 'permissionsCount', 'assignmentsCount'));
    }

    /**
     * Formulaire d'édition des permissions d'un rôle.
     */
    public function edit(Role $role): View
    {
        $permissions = Permission::orderBy('name')->get();

        // Regrouper les permissions par ressource (view, create, update, delete → ressource)
        $byResource = collect();
        foreach ($permissions as $permission) {
            $parts = explode(' ', $permission->name, 2);
            $resource = $parts[1] ?? $permission->name;
            $group = $byResource->get($resource, [
                'label' => $resource,
                'actions' => [],
            ]);
            $group['actions'][] = [
                'name' => $permission->name,
                'verb' => $parts[0] ?? '',
                'granted' => $role->hasPermissionTo($permission),
            ];
            $byResource->put($resource, $group);
        }

        return view('roles.edit', compact('role', 'permissions', 'byResource'));
    }

    /**
     * Enregistrement des permissions sélectionnées pour un rôle.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->name === 'super_admin') {
            return redirect()->route('roles.index')->with('error', 'Le rôle super admin possède toutes les permissions et ne peut pas être modifié.');
        }

        $permissions = Permission::pluck('name')->all();

        $granted = collect($request->input('permissions', []))
            ->filter(fn ($name) => in_array($name, $permissions, true))
            ->values()
            ->all();

        $role->syncPermissions($granted);

        return redirect()->route('roles.edit', $role)->with('success', "Les permissions du rôle « {$role->name} » ont été mises à jour (" . count($granted) . " permissions actives).");
    }
}
