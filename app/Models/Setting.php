<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Paramètres globaux de l'établissement (nom, adresse, contact, logo).
 *
 * Les valeurs sont mises en cache pour éviter un accès DB à chaque requête.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public $timestamps = false;

    /**
     * Clés autorisées et valeur par défaut.
     */
    public const KEYS = [
        'establishment_name' => 'Université',
        'establishment_address' => '',
        'establishment_phone' => '',
        'establishment_email' => '',
        'logo_path' => '',
    ];

    public const CACHE_KEY = 'site_settings';

    /**
     * Récupérer toutes les valeurs (avec cache).
     */
    public static function allValues(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            $defaults = self::KEYS;
            foreach (self::all() as $setting) {
                $defaults[$setting->key] = $setting->value;
            }
            return $defaults;
        });
    }

    /**
     * Récupérer une valeur.
     */
    public static function get(string $key, $default = null)
    {
        return self::allValues()[$key] ?? $default;
    }

    /**
     * Mettre à jour une valeur et vider le cache.
     */
    public static function set(string $key, string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Mettre à jour plusieurs valeurs d'un coup.
     */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            if (array_key_exists($key, self::KEYS)) {
                self::updateOrCreate(['key' => $key], ['value' => $value]);
            }
        }
        Cache::forget(self::CACHE_KEY);
    }
}
