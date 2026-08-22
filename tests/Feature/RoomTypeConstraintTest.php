<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Services\AutoGenerateTimetable;

class RoomTypeConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_types_are_placed_in_compatible_rooms()
    {
        // 1. Fixtures
        $yearId = DB::table('academic_years')->insertGetId(['name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => 1]);
        $deptId = DB::table('departments')->insertGetId(['name' => 'Sciences', 'code' => 'SCI']);
        $progId = DB::table('programs')->insertGetId(['department_id' => $deptId, 'name' => 'SVT', 'code' => 'SVT']);
        $semId = DB::table('semesters')->insertGetId(['program_id' => $progId, 'academic_year_id' => $yearId, 'name' => 'S1', 'number' => 1, 'weeks_count' => 15]);
        $groupId = DB::table('student_groups')->insertGetId(['semester_id' => $semId, 'name' => 'G1', 'capacity' => 30]);

        // Salles de différents types
        $roomAmphi = DB::table('classrooms')->insertGetId(['name' => 'Amphi A', 'capacity' => 100, 'type' => 'amphi']);
        $roomLabo = DB::table('classrooms')->insertGetId(['name' => 'Labo 1', 'capacity' => 40, 'type' => 'labo']);
        $roomCours = DB::table('classrooms')->insertGetId(['name' => 'Salle 1', 'capacity' => 40, 'type' => 'cours']);

        // Modules de différents types
        $modCours = DB::table('modules')->insertGetId(['program_id' => $progId, 'semester_id' => $semId, 'name' => 'Bio Cours', 'code' => 'BIO101', 'type' => 'cours', 'weekly_hours' => 2]);
        $modTP = DB::table('modules')->insertGetId(['program_id' => $progId, 'semester_id' => $semId, 'name' => 'Bio TP', 'code' => 'BIO101TP', 'type' => 'tp', 'weekly_hours' => 2]);

        $profId = DB::table('users')->insertGetId(['name' => 'Prof 1', 'email' => 'p1@test.com', 'password' => 'hash', 'role' => 'prof']);
        DB::table('professor_module')->insert(['professor_id' => $profId, 'module_id' => $modCours]);
        DB::table('professor_module')->insert(['professor_id' => $profId, 'module_id' => $modTP]);

        DB::table('days')->insert(['name' => 'Lundi', 'position' => 1]);
        DB::table('timeslots')->insert(['name' => 'T1', 'starts_at' => '08:30', 'ends_at' => '10:30', 'position' => 1]);
        DB::table('timeslots')->insert(['name' => 'T2', 'starts_at' => '10:30', 'ends_at' => '12:30', 'position' => 2]);

        // 2. Action
        $generator = new AutoGenerateTimetable();
        $result = $generator->generate($semId);

        // 3. Assertions
        $this->assertEquals(2, $result['sessions_generated'], "Doit générer 2 sessions");
        
        $sessionCours = DB::table('timetable_sessions')->where('module_id', $modCours)->first();
        $sessionTP = DB::table('timetable_sessions')->where('module_id', $modTP)->first();

        $roomCoursObj = DB::table('classrooms')->find($sessionCours->classroom_id);
        $roomTPObj = DB::table('classrooms')->find($sessionTP->classroom_id);

        $this->assertTrue(in_array($roomCoursObj->type, ['cours', 'amphi']), "Le cours doit être en salle cours ou amphi");
        $this->assertEquals('labo', $roomTPObj->type, "Le TP doit être en labo");
    }
}
