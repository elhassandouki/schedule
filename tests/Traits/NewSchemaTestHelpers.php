<?php

namespace Tests\Traits;

use App\Models\Module;
use App\Models\Semester;
use App\Models\StudentGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Helpers pour les tests basculés vers l'architecture Module / Professor (User) / StudentGroup.
 */
trait NewSchemaTestHelpers
{
    /**
     * Crée un professeur (User role=prof) et lui assigne les modules donnés,
     * ainsi que des disponibilités sur toute la semaine (08h00–17h00).
     */
    public function makeProfessor(string $name, array $modules = [], ?Semester $semester = null): User
    {
        $user = User::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '_', $name)) . '_' . Str::random(6) . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'prof',
        ]);

        if ($modules) {
            foreach ($modules as $m) {
                $user->modules()->syncWithoutDetaching([$m->id]);
            }
        }

        foreach ([0, 1, 2, 3, 4, 5, 6] as $day) {
            DB::table('professor_availabilities')->insert([
                'professor_id' => $user->id,
                'day_of_week' => $day,
                'start_minute' => 480,
                'end_minute' => 1020,
                'available' => true,
            ]);
        }

        return $user;
    }

    /**
     * Crée un module rattaché au semestre donné, avec un volume horaire et
     * des crédits par défaut.
     */
    public function makeModule(array $attributes, ?Semester $semester = null): Module
    {
        return Module::create(array_merge([
            'name' => 'Module ' . Str::random(4),
            'code' => Str::upper(Str::random(4)),
            'hours_per_week' => 2,
            'credits' => 3,
            'semester_id' => $semester ? $semester->id : null,
        ], $attributes));
    }

    /**
     * Crée un groupe d'étudiants pour le semestre.
     */
    public function makeGroup(array $attributes, ?Semester $semester = null): StudentGroup
    {
        return StudentGroup::create(array_merge([
            'name' => 'G-' . Str::random(4),
            'capacity' => 30,
            'semester_id' => $semester ? $semester->id : null,
        ], $attributes));
    }
}
