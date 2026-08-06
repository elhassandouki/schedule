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
use App\Models\TimetableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_professor_can_view_their_own_schedule_sessions(): void
    {
        $professor = User::create([
            'name' => 'Professeur Test',
            'email' => 'prof@example.com',
            'password' => bcrypt('secret'),
            'role' => 'prof',
        ]);
        $teacher = Teacher::create(['name' => 'Professeur Test', 'user_id' => $professor->id]);

        $department = Department::create(['name' => 'Informatique', 'code' => 'INFO']);
        $program = Program::create(['department_id' => $department->id, 'name' => 'Licence', 'code' => 'LIC']);
        $yearId = DB::table('academic_years')->insertGetId([
            'name' => '2026/2027',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $semester = Semester::create([
            'program_id' => $program->id,
            'academic_year_id' => $yearId,
            'name' => 'S1',
            'number' => 1,
        ]);
        $subject = Subject::create(['semester_id' => $semester->id, 'teacher_id' => $teacher->id, 'name' => 'Algorithmes', 'code' => 'ALG001']);
        $section = Section::create(['program_id' => $program->id, 'name' => 'G1', 'capacity' => 30]);
        $classroom = Classroom::create(['name' => 'A101', 'capacity' => 40, 'type' => 'classroom']);
        $day = SchoolDay::create(['name' => 'Monday', 'position' => 1]);
        $timeslot = Timeslot::create(['name' => '08:00-10:00', 'starts_at' => '08:00', 'ends_at' => '10:00', 'position' => 1]);

        TimetableSession::create([
            'semester_id' => $semester->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $classroom->id,
            'section_id' => $section->id,
            'day_id' => $day->id,
            'timeslot_id' => $timeslot->id,
        ]);

        $response = $this->actingAs($professor)->get(route('timetable.show', $semester));

        $response->assertOk();
        $response->assertSee('Algorithmes');
        $response->assertSee('G1');
    }
}
