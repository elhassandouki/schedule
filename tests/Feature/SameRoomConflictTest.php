<?php

namespace Tests\Feature;

use App\Models\{Classroom, StudentGroup, Timeslot, User};
use App\Services\AutoGenerateTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cas où la salle d'un module PEUT changer : la salle assignée est occupée par
 * une autre filière au moment d'une séance. On vérifie le comportement et la
 * stabilité après re-planification.
 */
class SameRoomConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_changes_only_when_assigned_room_is_taken_by_another_program(): void
    {
        $now = now();
        DB::table('academic_years')->insert(['name' => '2026-2027', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('departments')->insert(['name' => 'Département Test', 'code' => 'DT', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('programs')->insert(['department_id' => 1, 'name' => 'PC', 'code' => 'PC', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('programs')->insert(['department_id' => 1, 'name' => 'Maths', 'code' => 'MT', 'created_at' => $now, 'updated_at' => $now]);
        $semPC = DB::table('semesters')->insertGetId(['program_id' => 1, 'number' => 1, 'name' => 'S1', 'weeks_count' => 1, 'academic_year_id' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $semMT = DB::table('semesters')->insertGetId(['program_id' => 2, 'number' => 1, 'name' => 'S1', 'weeks_count' => 1, 'academic_year_id' => 1, 'created_at' => $now, 'updated_at' => $now]);

        $profPC = User::create(['name' => 'Prof PC', 'email' => 'pc@example.com', 'password' => bcrypt('password'), 'role' => 'prof']);
        $profMT = User::create(['name' => 'Prof MT', 'email' => 'mt@example.com', 'password' => bcrypt('password'), 'role' => 'prof']);
        $modPC = DB::table('modules')->insertGetId(['program_id' => 1, 'semester_id' => $semPC, 'name' => 'Mécanique', 'code' => 'M1', 'weekly_hours' => 3, 'created_at' => $now, 'updated_at' => $now]);
        $modMT = DB::table('modules')->insertGetId(['program_id' => 2, 'semester_id' => $semMT, 'name' => 'Analyse', 'code' => 'A1', 'weekly_hours' => 3, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('professor_module')->insert(['professor_id' => $profPC->id, 'module_id' => $modPC, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('professor_module')->insert(['professor_id' => $profMT->id, 'module_id' => $modMT, 'created_at' => $now, 'updated_at' => $now]);

        DB::table('days')->insert(['name' => 'Monday', 'position' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('days')->insert(['name' => 'Tuesday', 'position' => 2, 'created_at' => $now, 'updated_at' => $now]);
        Timeslot::create(['name' => '09:00-10:30', 'starts_at' => '09:00', 'ends_at' => '10:30', 'position' => 1]);
        Timeslot::create(['name' => '11:00-12:30', 'starts_at' => '11:00', 'ends_at' => '12:30', 'position' => 2]);

        StudentGroup::create(['semester_id' => $semPC, 'name' => 'PC G1', 'capacity' => 30]);
        StudentGroup::create(['semester_id' => $semMT, 'name' => 'MT G1', 'capacity' => 30]);

        Classroom::create(['name' => 'Bloc A - Salle 1', 'capacity' => 40, 'type' => 'classroom']);

        // Maths réserve la seule salle le lundi.
        $genMT = app(AutoGenerateTimetable::class)->generate($semMT);
        $this->assertTrue($genMT['success']);

        // PC doit placer Mécanique : la salle est prise le lundi mais libre mardi.
        $genPC = app(AutoGenerateTimetable::class)->generate($semPC);
        $this->assertTrue($genPC['success']);

        $sessionsPC = DB::table('timetable_sessions as s')
            ->join('classrooms as c', 'c.id', '=', 's.classroom_id')
            ->where('s.semester_id', $semPC)
            ->select('c.name as salle', 's.day_id', 's.timeslot_id')
            ->get();

        foreach ($sessionsPC as $s) echo "PC Mécanique | {$s->salle} | day {$s->day_id} slot {$s->timeslot_id}\n";

        $this->assertEquals(2, $sessionsPC->count(), 'Mécanique doit avoir 2 séances de 1h30.');
        $this->assertCount(1, $sessionsPC->pluck('salle')->unique(), 'Mécanique doit garder la même salle malgré la réservation par Maths le lundi.');
    }
}
