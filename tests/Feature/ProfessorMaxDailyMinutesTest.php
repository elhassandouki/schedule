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

class ProfessorMaxDailyMinutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_professor_does_not_exceed_max_daily_minutes()
    {
        $now = now();
        DB::table('academic_years')->insert(['name' => '2026-2027', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('departments')->insert(['name' => 'IT', 'code' => 'IT', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('programs')->insert(['department_id' => 1, 'name' => 'CS', 'code' => 'CS', 'created_at' => $now, 'updated_at' => $now]);
        $sem = DB::table('semesters')->insertGetId(['program_id' => 1, 'number' => 1, 'name' => 'S1', 'weeks_count' => 1, 'academic_year_id' => 1, 'created_at' => $now, 'updated_at' => $now]);

        // Prof avec un max de 120 minutes par jour (2h)
        $prof = User::create(['name' => 'Prof', 'email' => 'prof@test.com', 'password' => 'hash', 'role' => 'prof', 'max_daily_minutes' => 120]);
        
        // Module de 10h/semaine pour forcer le remplissage
        $mod = DB::table('modules')->insertGetId(['program_id' => 1, 'semester_id' => $sem, 'name' => 'Mod', 'code' => 'M1', 'type' => 'cours', 'weekly_hours' => 10, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('professor_module')->insert(['professor_id' => $prof->id, 'module_id' => $mod]);

        DB::table('days')->insert(['name' => 'Monday', 'position' => 1]);
        
        // 4 créneaux de 2h chacun le même jour
        Timeslot::create(['name' => 'T1', 'starts_at' => '08:00', 'ends_at' => '10:00', 'position' => 1]);
        Timeslot::create(['name' => 'T2', 'starts_at' => '10:00', 'ends_at' => '12:00', 'position' => 2]);
        Timeslot::create(['name' => 'T3', 'starts_at' => '13:00', 'ends_at' => '15:00', 'position' => 3]);
        Timeslot::create(['name' => 'T4', 'starts_at' => '15:00', 'ends_at' => '17:00', 'position' => 4]);

        StudentGroup::create(['semester_id' => $sem, 'name' => 'G1', 'capacity' => 20]);
        Classroom::create(['name' => 'R1', 'capacity' => 40, 'type' => 'cours']);

        app(AutoGenerateTimetable::class)->generate($sem);

        // Le prof ne doit avoir qu'UNE SEULE session de 2h le lundi, car 2 sessions feraient 240 min > 120 min.
        $sessionsCount = DB::table('timetable_sessions')->where('professor_id', $prof->id)->count();
        $this->assertEquals(1, $sessionsCount, 'Le professeur ne doit pas dépasser son budget quotidien de 120 minutes.');
    }
}
