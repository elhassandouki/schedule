<?php

namespace Tests\Feature;

use App\Services\AutoGenerateTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Vérifie qu'aucune salle (ni prof, ni groupe) n'est double-bookée même lorsque
 * deux créneaux aux horaires qui se chevauchent existent (timeslot_id différents).
 */
class OverlappingSlotRoomConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_generator_never_double_books_a_room_with_overlapping_slots(): void
    {
        $this->seed(\Database\Seeders\UnifiedDemoSeeder::class);
        DB::table('timetable_sessions')->delete();

        $now = now();
        DB::table('timeslots')->delete();
        // Deux créneaux qui se chevauchent partiellement : 16:00-17:30 et 16:30-18:00.
        // Le générateur ne doit jamais placer deux sessions dans la même salle sur
        // ces deux créneaux (les horaires se chevauchent).
        DB::table('timeslots')->insert([
            ['name' => '08:00-09:30', 'starts_at' => '08:00', 'ends_at' => '09:30', 'position' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '10:00-11:30', 'starts_at' => '10:00', 'ends_at' => '11:30', 'position' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '13:00-14:30', 'starts_at' => '13:00', 'ends_at' => '14:30', 'position' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '16:00-17:30', 'starts_at' => '16:00', 'ends_at' => '17:30', 'position' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '16:30-18:00', 'starts_at' => '16:30', 'ends_at' => '18:00', 'position' => 5, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Module de 6h → 4 sessions de 90 min nécessaires. Une seule salle du demo
        // a une capacité suffisante ; les 4 sessions seront forcées d'utiliser les
        // mêmes jours/créneaux → le chevauchement 16:00-17:30 × 16:30-18:00 est mis
        // à l'épreuve.
        $module = DB::table('modules')->where('weekly_hours', 6)->first();
        $profId = (int) DB::table('users')->where('role', 'prof')->min('id');

        DB::table('professor_module')->delete();
        DB::table('professor_module')->insert([
            'professor_id' => $profId, 'module_id' => $module->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('professor_availabilities')->where('professor_id', $profId)->delete();
        foreach (range(1, 5) as $day) {
            DB::table('professor_availabilities')->insert([
                'professor_id' => $profId, 'day_of_week' => $day,
                'start_minute' => 480, 'end_minute' => 1080, 'available' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Ne garder qu'un seul groupe pour concentrer les sessions.
        $groupId = (int) DB::table('student_groups')->min('id');
        DB::table('student_groups')->where('id', '!=', $groupId)->delete();
        $semesterId = (int) DB::table('student_groups')->where('id', $groupId)->value('semester_id');

        $report = (new AutoGenerateTimetable())->generate($semesterId);

        // Détection de tout chevauchement horaire entre sessions sur la même salle,
        // le même prof ou le même groupe (timeslot_id différents mais horaires qui se
        // chevauchent).
        $clash = DB::table('timetable_sessions as s1')
            ->join('timetable_sessions as s2', fn ($j) => $j->on('s1.day_id', '=', 's2.day_id'))
            ->join('timeslots as t1', 't1.id', '=', 's1.timeslot_id')
            ->join('timeslots as t2', 't2.id', '=', 's2.timeslot_id')
            ->where('s1.semester_id', $semesterId)->where('s2.semester_id', $semesterId)
            ->whereColumn('s1.id', '<', 's2.id')
            ->whereColumn('t1.starts_at', '<', 't2.ends_at')
            ->whereColumn('t1.ends_at', '>', 't2.starts_at')
            ->where(fn ($q) => $q
                ->whereColumn('s1.classroom_id', '=', 's2.classroom_id')
                ->orWhereColumn('s1.professor_id', '=', 's2.professor_id')
                ->orWhereColumn('s1.student_group_id', '=', 's2.student_group_id'))
            ->count();

        $this->assertSame(0, $clash, 'Sessions qui se chevauchent sur la même salle / prof / groupe');
    }
}
