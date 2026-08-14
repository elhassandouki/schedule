<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gestion des informations de l'établissement (nom, adresse, contact, logo).
 *
 * Accessible uniquement aux rôles super_admin et sous_admin.
 */
class SettingsController extends Controller
{
    /**
     * Formulaire des paramètres de l'établissement.
     */
    public function edit(): View
    {
        $settings = Setting::allValues();
        return view('settings.edit', compact('settings'));
    }

    /**
     * Enregistrement des paramètres.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'establishment_name' => ['required', 'string', 'max:255'],
            'establishment_address' => ['nullable', 'string', 'max:500'],
            'establishment_phone' => ['nullable', 'string', 'max:50'],
            'establishment_email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:2048'],
        ], [
            'establishment_name.required' => 'Le nom de l\'établissement est obligatoire.',
            'establishment_email.email' => 'L\'adresse e-mail n\'est pas valide.',
            'logo.image' => 'Le fichier doit être une image (JPEG, PNG, JPG, WEBP ou GIF).',
            'logo.max' => 'Le logo ne doit pas dépasser 2 Mo.',
        ]);

        // Upload du logo (remplace l'ancien s'il existe)
        if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
            $oldLogo = Setting::get('logo_path', '');
            if ($oldLogo && \Storage::disk('public')->exists($oldLogo)) {
                \Storage::disk('public')->delete($oldLogo);
            }
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        } else {
            $data['logo_path'] = Setting::get('logo_path', '');
        }

        Setting::setMany([
            'establishment_name' => $data['establishment_name'],
            'establishment_address' => $data['establishment_address'],
            'establishment_phone' => $data['establishment_phone'],
            'establishment_email' => $data['establishment_email'],
            'logo_path' => $data['logo_path'],
        ]);

        return redirect()->route('settings.edit')->with('success', 'Les informations de l\'établissement ont été mises à jour.');
    }

    /**
     * Suppression du logo (retour au logo par défaut).
     */
    public function removeLogo(): RedirectResponse
    {
        $oldLogo = Setting::get('logo_path', '');
        if ($oldLogo && \Storage::disk('public')->exists($oldLogo)) {
            \Storage::disk('public')->delete($oldLogo);
        }
        Setting::set('logo_path', '');

        return redirect()->route('settings.edit')->with('success', 'Le logo a été supprimé.');
    }
}
