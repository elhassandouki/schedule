<?php

namespace Tests\Feature;

use App\Models\{Classroom, StudentGroup, Timeslot, User};
use App\Services\AutoGenerateTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prouve la réservation GLOBALE des salles entre filières : si une salle est
 * déjà réservée par une autre filière (semestre/programme différent) au même
 * moment, le générateur ne peut pas l'attribuer. La salle est considérée comme
 * occupée au niveau de l'établissement, pas seulement du semestre.
 */
class GlobalRoomReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_room_reserved_by_another_program_is_unavailable(): void
    {
        $now = now();
        // Deux filières (programmes différents), chacune avec son semestre S1.
        DB::table("academic_years")->insert(["name" => "2026-2027", "created_at" => $now, "updated_at" => $now]);
        DB::table("departments")->insert(["name" => "Département Test", "code" => "DT", "created_at" => $now, "updated_at" => $now]);
        DB::table("programs")->insert(["department_id" => 1, "name" => "Programme A", "code" => "PA", "created_at" => $now, "updated_at" => $now]);
        DB::table("programs")->insert(["department_id" => 1, "name" => "Programme B", "code" => "PB", "created_at" => $now, "updated_at" => $now]);
        $semA = DB::table('semesters')->insertGetId(['program_id' => 1, 'number' => 1, 'name' => 'S1', 'weeks_count' => 1, 'academic_year_id' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $semB = DB::table('semesters')->insertGetId(['program_id' => 2, 'number' => 1, 'name' => 'S1', 'weeks_count' => 1, 'academic_year_id' => 1, 'created_at' => $now, 'updated_at' => $now]);

        $modA = DB::table('modules')->insertGetId(['program_id' => 1, 'semester_id' => $semA, 'name' => 'Maths A', 'code' => 'MA-X', 'weekly_hours' => 2, 'created_at' => $now, 'updated_at' => $now]);
        $modB = DB::table('modules')->insertGetId(['program_id' => 2, 'semester_id' => $semB, 'name' => 'Physique B', 'code' => 'PH-X', 'weekly_hours' => 2, 'created_at' => $now, 'updated_at' => $now]);

        $profA = User::create(['name' => 'Prof A', 'email' => 'profa@example.com', 'password' => bcrypt('password'), 'role' => 'prof']);
        $profB = User::create(['name' => 'Prof B', 'email' => 'profb@example.com', 'password' => bcrypt('password'), 'role' => 'prof']);
        DB::table('professor_module')->insert(['professor_id' => $profA->id, 'module_id' => $modA, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('professor_module')->insert(['professor_id' => $profB->id, 'module_id' => $modB, 'created_at' => $now, 'updated_at' => $now]);

        DB::table('days')->insert(['name' => 'Monday', 'position' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $slot = Timeslot::create(['name' => '09:00-11:00', 'starts_at' => '09:00', 'ends_at' => '11:00', 'position' => 1]);

        $grpA = StudentGroup::create(['semester_id' => $semA, 'name' => 'G1A', 'capacity' => 20]);
        $grpB = StudentGroup::create(['semester_id' => $semB, 'name' => 'G1B', 'capacity' => 20]);

        // Une seule salle commune aux deux filières.
        Classroom::create(['name' => 'Salle Unique', 'capacity' => 40, 'type' => 'classroom']);
        Classroom::create(['name' => 'Amphi', 'capacity' => 200, 'type' => 'amphitheatre']);

        // Filière A d'abord : elle réserve "Salle Unique" le lundi 09:00-11:00.
        $reportA = app(AutoGenerateTimetable::class)->generate($semA);
        $this->assertTrue($reportA['success'], json_encode($reportA['skipped_per_module']));

        $sessionA = DB::table('timetable_sessions')->where('semester_id', $semA)->first();
        $this->assertSame('Salle Unique', DB::table('classrooms')->where('id', $sessionA->classroom_id)->value('name'));

        // Filière B ensuite : "Salle Unique" doit être bloquée (réservée par A),
        // elle doit utiliser l'Amphi au même créneau.
        $reportB = app(AutoGenerateTimetable::class)->generate($semB);
        $this->assertTrue($reportB['success'], json_encode($reportB['skipped_per_module']));

        $sessionB = DB::table('timetable_sessions')->where('semester_id', $semB)->first();
        $this->assertSame('Amphi', DB::table('classrooms')->where('id', $sessionB->classroom_id)->value('name'));
    }
}
