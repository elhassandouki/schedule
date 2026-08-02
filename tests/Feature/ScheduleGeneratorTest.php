<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Program;
use App\Models\SchoolDay;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Timeslot;
use App\Models\User;
use App\Services\AutoGenerateTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacyScheduleGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_generation_writes_timetable_sessions_for_the_semester(): void
    {
        $professor = User::create([
            'name' => 'Prof Test',
            'email' => 'prof@example.com',
            'password' => bcrypt('secret'),
            'role' => 'prof',
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

        $teacher = Teacher::create(['name' => 'Prof Test', 'user_id' => $professor->id]);
        Subject::create(['semester_id' => $semester->id, 'teacher_id' => $teacher->id, 'name' => 'Algorithmes', 'code' => 'ALG001', 'sessions_per_week' => 1]);
        Section::create(['program_id' => $program->id, 'name' => 'G1', 'capacity' => 30]);
        Classroom::create(['name' => 'A101', 'capacity' => 50, 'type' => 'classroom']);
        SchoolDay::create(['name' => 'Monday', 'position' => 1]);
        SchoolDay::create(['name' => 'Tuesday', 'position' => 2]);
        Timeslot::create(['name' => '08:00-10:00', 'starts_at' => '08:00', 'ends_at' => '10:00', 'position' => 1]);
        Timeslot::create(['name' => '10:00-12:00', 'starts_at' => '10:00', 'ends_at' => '12:00', 'position' => 2]);

        $report = (new AutoGenerateTimetable())->generate($semester->id);

        $this->assertTrue($report['success']);
        $this->assertSame(1, DB::table('timetable_sessions')->where('semester_id', $semester->id)->count());
        $this->assertSame(0, DB::table('timetable_entries')->count());
    }
}
