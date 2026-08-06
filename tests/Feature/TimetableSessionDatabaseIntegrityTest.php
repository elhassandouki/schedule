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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimetableSessionDatabaseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function seedSemesterContext(): array
    {
        $department = Department::create(['name' => 'Informatique', 'code' => 'INFO']);
        $program = Program::create(['department_id' => $department->id, 'name' => 'Licence', 'code' => 'LIC']);
        $academicYearId = DB::table('academic_years')->insertGetId([
            'name' => '2026/2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $semester = Semester::create([
            'program_id' => $program->id,
            'academic_year_id' => $academicYearId,
            'name' => 'S1',
            'number' => 1,
        ]);
        $teacher = Teacher::create(['name' => 'Prof A']);
        $subject = Subject::create(['semester_id' => $semester->id, 'teacher_id' => $teacher->id, 'name' => 'Algorithmes', 'code' => 'ALG', 'sessions_per_week' => 1]);
        $section = Section::create(['program_id' => $program->id, 'name' => 'G1', 'capacity' => 30]);
        $classroom = Classroom::create(['name' => 'A101', 'capacity' => 40, 'type' => 'classroom']);
        $day = SchoolDay::create(['name' => 'Monday', 'position' => 1]);
        $timeslot = Timeslot::create(['name' => '08:00-10:00', 'starts_at' => '08:00', 'ends_at' => '10:00', 'position' => 1]);

        return compact('semester', 'subject', 'teacher', 'section', 'classroom', 'day', 'timeslot');
    }

    public function test_teacher_double_booking_is_prevented_by_unique_constraint(): void
    {
        $data = $this->seedSemesterContext();
        TimetableSession::create([
            'subject_id' => $data['subject']->id,
            'teacher_id' => $data['teacher']->id,
            'classroom_id' => $data['classroom']->id,
            'section_id' => $data['section']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        TimetableSession::create([
            'subject_id' => $data['subject']->id,
            'teacher_id' => $data['teacher']->id,
            'classroom_id' => $data['classroom']->id + 1,
            'section_id' => $data['section']->id + 1,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);
    }

    public function test_classroom_double_booking_is_prevented_by_unique_constraint(): void
    {
        $data = $this->seedSemesterContext();
        TimetableSession::create([
            'subject_id' => $data['subject']->id,
            'teacher_id' => $data['teacher']->id,
            'classroom_id' => $data['classroom']->id,
            'section_id' => $data['section']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        TimetableSession::create([
            'subject_id' => $data['subject']->id,
            'teacher_id' => $data['teacher']->id + 1,
            'classroom_id' => $data['classroom']->id,
            'section_id' => $data['section']->id + 1,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);
    }

    public function test_section_double_booking_is_prevented_by_unique_constraint(): void
    {
        $data = $this->seedSemesterContext();
        TimetableSession::create([
            'subject_id' => $data['subject']->id,
            'teacher_id' => $data['teacher']->id,
            'classroom_id' => $data['classroom']->id,
            'section_id' => $data['section']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        TimetableSession::create([
            'subject_id' => $data['subject']->id,
            'teacher_id' => $data['teacher']->id + 1,
            'classroom_id' => $data['classroom']->id + 1,
            'section_id' => $data['section']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);
    }

    public function test_foreign_key_constraints_are_enforced_for_related_entities(): void
    {
        $data = $this->seedSemesterContext();
        $this->expectException(\Illuminate\Database\QueryException::class);
        TimetableSession::create([
            'subject_id' => 999999,
            'teacher_id' => $data['teacher']->id,
            'classroom_id' => $data['classroom']->id,
            'section_id' => $data['section']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);
    }
}
