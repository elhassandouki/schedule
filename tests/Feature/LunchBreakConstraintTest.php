<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\StudentGroup;
use App\Models\Timeslot;
use App\Models\User;
use App\Services\AutoGenerateTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LunchBreakConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_generator_skips_lunch_break_slots()
    {
        $now = now();
        DB::table('academic_years')->insert(['name' => '2026-2027', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('departments')->insert(['name' => 'IT', 'code' => 'IT', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('programs')->insert(['department_id' => 1, 'name' => 'CS', 'code' => 'CS', 'created_at' => $now, 'updated_at' => $now]);
        $sem = DB::table('semesters')->insertGetId(['program_id' => 1, 'number' => 1, 'name' => 'S1', 'weeks_count' => 1, 'academic_year_id' => 1, 'created_at' => $now, 'updated_at' => $now]);

        $prof = User::create(['name' => 'Prof', 'email' => 'prof@test.com', 'password' => 'hash', 'role' => 'prof']);
        $mod = DB::table('modules')->insertGetId(['program_id' => 1, 'semester_id' => $sem, 'name' => 'Mod', 'code' => 'M1', 'type' => 'cours', 'weekly_hours' => 10, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('professor_module')->insert(['professor_id' => $prof->id, 'module_id' => $mod]);

        DB::table('days')->insert(['name' => 'Monday', 'position' => 1]);
        
        // Slot 1: Normal
        $ts1 = Timeslot::create(['name' => '08:00-10:00', 'starts_at' => '08:00', 'ends_at' => '10:00', 'position' => 1, 'is_lunch_break' => false]);
        // Slot 2: Lunch Break
        $ts2 = Timeslot::create(['name' => '12:00-13:00', 'starts_at' => '12:00', 'ends_at' => '13:00', 'position' => 2, 'is_lunch_break' => true]);
        // Slot 3: Normal
        $ts3 = Timeslot::create(['name' => '14:00-16:00', 'starts_at' => '14:00', 'ends_at' => '16:00', 'position' => 3, 'is_lunch_break' => false]);

        StudentGroup::create(['semester_id' => $sem, 'name' => 'G1', 'capacity' => 20]);
        Classroom::create(['name' => 'R1', 'capacity' => 40, 'type' => 'cours']);

        $report = app(AutoGenerateTimetable::class)->generate($sem);

        // Le slot 2 ne doit avoir AUCUNE session
        $lunchSessions = DB::table('timetable_sessions')->where('timeslot_id', $ts2->id)->count();
        $this->assertEquals(0, $lunchSessions, 'Le créneau de pause déjeuner ne doit pas contenir de sessions.');
        
        // Les autres slots doivent être utilisés (car weekly_hours=10 est élevé)
        $totalSessions = DB::table('timetable_sessions')->count();
        $this->assertGreaterThan(0, $totalSessions);
    }
}
