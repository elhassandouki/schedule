<?php

namespace Tests\Feature;

use App\Models\{Classroom, StudentGroup, Timeslot, User};
use App\Services\AutoGenerateTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cas où le prof est indisponible à certains créneaux : le module doit quand même
 * garder la même salle pour ses sessions consécutives ou réparties.
 */
class ConsecutiveSessionRoomStabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_stable_when_prof_unavailable_at_some_slots(): void
    {
        $now = now();
        DB::table('academic_years')->insert(['name' => '2026-2027', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('departments')->insert(['name' => 'SVT', 'code' => 'SVT', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('programs')->insert(['department_id' => 1, 'name' => 'SVT', 'code' => 'SVT', 'created_at' => $now, 'updated_at' => $now]);
        $sem = DB::table('semesters')->insertGetId(['program_id' => 1, 'number' => 1, 'name' => 'S1', 'weeks_count' => 15, 'academic_year_id' => 1, 'created_at' => $now, 'updated_at' => $now]);

        $prof = User::create(['name' => 'Prof PR3', 'email' => 'pr3@example.com', 'password' => bcrypt('password'), 'role' => 'prof']);
        $mod = DB::table('modules')->insertGetId(['program_id' => 1, 'semester_id' => $sem, 'name' => 'Biologie', 'code' => 'M1', 'weekly_hours' => 3, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('professor_module')->insert(['professor_id' => $prof->id, 'module_id' => $mod, 'created_at' => $now, 'updated_at' => $now]);

        // Prof disponible UNIQUEMENT le lundi (day position 1).
        DB::table('professor_availabilities')->insert([
            'professor_id' => $prof->id, 'day_of_week' => 1, 'available' => true,
            'start_minute' => 0, 'end_minute' => 1440, 'created_at' => $now, 'updated_at' => $now,
        ]);

        DB::table('days')->insert(['name' => 'Monday', 'position' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('days')->insert(['name' => 'Tuesday', 'position' => 2, 'created_at' => $now, 'updated_at' => $now]);
        Timeslot::create(['name' => '09:00-10:30', 'starts_at' => '09:00', 'ends_at' => '10:30', 'position' => 1]);
        Timeslot::create(['name' => '11:00-12:30', 'starts_at' => '11:00', 'ends_at' => '12:30', 'position' => 2]);

        StudentGroup::create(['semester_id' => $sem, 'name' => 'G1', 'capacity' => 35]);
        Classroom::create(['name' => 'Salle 01', 'capacity' => 40, 'type' => 'classroom']);
        Classroom::create(['name' => 'Salle 02', 'capacity' => 40, 'type' => 'classroom']);

        $report = app(AutoGenerateTimetable::class)->generate($sem);
        echo "Report: generated={$report['sessions_generated']}, skipped={$report['sessions_skipped']}\n";

        $sessions = DB::table('timetable_sessions as s')
            ->join('classrooms as c', 'c.id', '=', 's.classroom_id')
            ->join('timeslots as t', 't.id', '=', 's.timeslot_id')
            ->where('s.semester_id', $sem)
            ->select('c.name as salle', 't.position as creneau', 's.day_id')
            ->orderBy('t.position')->get();

        foreach ($sessions as $s) echo "Session | Salle: {$s->salle} | Creneau: {$s->creneau} | Day: {$s->day_id}\n";

        $this->assertEquals(2, $sessions->count());
        $this->assertCount(1, $sessions->pluck('salle')->unique(), 'Les 2 sessions consécutives doivent être dans la MÊME salle.');
    }
}
