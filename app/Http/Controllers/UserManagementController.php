<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Gestion des utilisateurs et de leurs rôles (spatie/laravel-permission).
 *
 * Accessible uniquement aux rôles super_admin et sous_admin
 * (middleware role:super_admin,sous_admin dans web.php).
 */
class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::with('roles')->orderByDesc('id')->paginate(15);
        $roles = Role::orderBy('name')->get();

        return view('users.index', compact('users', 'roles'));
    }

    public function edit(User $user)
    {
        $roles = Role::orderBy('name')->get();

        return view('users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role' => ['required', 'string', 'in:' . implode(',', User::ROLES)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'email.unique' => 'Cette adresse e-mail est déjà utilisée par un autre compte.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'Les mots de passe ne correspondent pas.',
            'role.in' => 'Ce rôle n\'est pas valide.',
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        // Synchroniser le rôle spatie avec le champ legacy `role`
        $user->syncSpatieRole();

        return redirect()->route('users.index')->with('success', "L'utilisateur {$user->name} a été mis à jour avec le rôle {$data['role']}.");
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')
                ->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('users.index')->with('success', "L'utilisateur {$name} a été supprimé.");
    }
}
