<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'role', 'department_id', 'program_id', 'max_weekly_hours', 'max_daily_minutes'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    public const ROLES = ['super_admin', 'sous_admin', 'chef_departement', 'chef_filiere', 'prof'];

    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'sous_admin'], true);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'professor_module', 'professor_id')->withTimestamps();
    }

    /**
     * Synchroniser le rôle spatie avec le champ `role` du modèle.
     * Appeler après chaque création/mise à jour d'utilisateur pour que le
     * système de permissions reste cohérent avec la colonne legacy `role`.
     */
    public function syncSpatieRole(): void
    {
        if (in_array($this->role, self::ROLES, true)) {
            $this->assignRole($this->role);
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
