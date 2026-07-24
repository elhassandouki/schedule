<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Module;
use App\Models\Program;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\StudentGroup;
use App\Models\TeachingSession;
use App\Models\User;
use App\Services\ScheduleGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScheduleGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_professor_weekly_hours_limit_prevents_overbooking(): void
    {
        $professor = User::create([
            'name' => 'Prof Test',
            'email' => 'prof@example.com',
            'password' => bcrypt('secret'),
            'role' => 'prof',
            'max_weekly_hours' => 1,
        ]);

        $department = Department::create(['name' => 'Informatique', 'code' => 'INFO']);
        $program = Program::create(['department_id' => $department->id, 'name' => 'Licence', 'code' => 'LIC']);
        $academicYear = DB::table('academic_years')->insertGetId([
            'name' => '2026/2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $semester = Semester::create([
            'program_id' => $program->id,
            'academic_year_id' => $academicYear,
            'name' => 'S1',
            'number' => 1,
        ]);

        $module = Module::create([
            'program_id' => $program->id,
            'name' => 'Algorithmes',
            'code' => 'ALG001',
            'weekly_hours' => 2,
        ]);
        $group = StudentGroup::create([
            'semester_id' => $semester->id,
            'name' => 'G1',
            'student_count' => 30,
        ]);
        DB::table('classrooms')->insert([
            'name' => 'A101',
            'capacity' => 50,
            'type' => 'cours',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $session = TeachingSession::create([
            'semester_id' => $semester->id,
            'module_id' => $module->id,
            'professor_id' => $professor->id,
            'student_group_id' => $group->id,
            'type' => 'cours',
            'duration_minutes' => 60,
            'occurrences_per_week' => 2,
        ]);

        [$schedule, $unplaced] = (new ScheduleGenerator())->generate($semester->id, 'Test emploi');

        $this->assertInstanceOf(Schedule::class, $schedule);
        $this->assertCount(1, $unplaced);
        $this->assertSame($session->id, $unplaced[0]['session_id']);
        $this->assertSame(2, $unplaced[0]['occurrence']);
        $this->assertSame(1, DB::table('timetable_entries')->where('teaching_session_id', $session->id)->count());
    }
}
