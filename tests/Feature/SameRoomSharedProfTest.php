<?php

namespace Tests\Feature;

use App\Models\{Classroom, StudentGroup, Timeslot, User};
use App\Services\AutoGenerateTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cas le plus probable en prod : un prof enseigne PLUSIEURS modules du même
 * semestre/groupe. Quand le prof a deux modules qui se suivent, la salle du
 * premier module est occupée à ce créneau par... lui-même ? Vérifier que le
 * contrôle overlaps salle bloque le 2e module d'utiliser la salle du 1er
 * module si le prof est déjà en cours dans cette salle (le même prof dans la
 * même salle au même moment = conflit salle ? non, le conflit salle = 2
 * sessions différentes sur la même salle au même moment, ce qui est exact).
 * Ce test vérifie le comportement réel.
 */
class SameRoomSharedProfTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_stable_when_prof_teaches_two_modules(): void
    {
        $now = now();
        DB::table('academic_years')->insert(['name' => '2026-2027', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('departments')->insert(['name' => 'Département Test', 'code' => 'DT', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('programs')->insert(['department_id' => 1, 'name' => 'PC', 'code' => 'PC', 'created_at' => $now, 'updated_at' => $now]);
        $sem = DB::table('semesters')->insertGetId(['program_id' => 1, 'number' => 1, 'name' => 'S1', 'weeks_count' => 1, 'academic_year_id' => 1, 'created_at' => $now, 'updated_at' => $now]);

        // UN SEUL prof enseigne deux modules (cas classique).
        $prof = User::create(['name' => 'Prof', 'email' => 'p@example.com', 'password' => bcrypt('password'), 'role' => 'prof']);
        $mod1 = DB::table('modules')->insertGetId(['program_id' => 1, 'semester_id' => $sem, 'name' => 'Mécanique', 'code' => 'M1', 'weekly_hours' => 3, 'created_at' => $now, 'updated_at' => $now]);
        $mod2 = DB::table('modules')->insertGetId(['program_id' => 1, 'semester_id' => $sem, 'name' => 'Thermodynamique', 'code' => 'M2', 'weekly_hours' => 3, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('professor_module')->insert(['professor_id' => $prof->id, 'module_id' => $mod1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('professor_module')->insert(['professor_id' => $prof->id, 'module_id' => $mod2, 'created_at' => $now, 'updated_at' => $now]);

        DB::table('days')->insert(['name' => 'Monday', 'position' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('days')->insert(['name' => 'Tuesday', 'position' => 2, 'created_at' => $now, 'updated_at' => $now]);
        Timeslot::create(['name' => '09:00-10:30', 'starts_at' => '09:00', 'ends_at' => '10:30', 'position' => 1]);
        Timeslot::create(['name' => '11:00-12:30', 'starts_at' => '11:00', 'ends_at' => '12:30', 'position' => 2]);

        StudentGroup::create(['semester_id' => $sem, 'name' => 'G1', 'capacity' => 30]);

        Classroom::create(['name' => 'Bloc A - Salle 1', 'capacity' => 40, 'type' => 'classroom']);
        Classroom::create(['name' => 'Bloc A - Salle 2', 'capacity' => 40, 'type' => 'classroom']);

        $report = app(AutoGenerateTimetable::class)->generate($sem);

        $sessions = DB::table('timetable_sessions as s')
            ->join('modules as m', 'm.id', '=', 's.module_id')
            ->join('classrooms as c', 'c.id', '=', 's.classroom_id')
            ->where('s.semester_id', $sem)
            ->select('m.name as module', 'c.name as salle', 's.day_id', 's.timeslot_id')
            ->orderBy('m.id')->orderBy('s.day_id')->orderBy('s.timeslot_id')->get();

        echo "\n";
        foreach ($sessions as $s) echo "{$s->module} | {$s->salle} | day {$s->day_id} slot {$s->timeslot_id}\n";

        foreach ($sessions->groupBy('module') as $modName => $rows) {
            $this->assertCount(1, $rows->pluck('salle')->unique(), "Le module $modName doit garder la même salle.");
        }
    }
}
