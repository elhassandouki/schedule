<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Module;
use App\Models\Program;
use App\Models\SchoolDay;
use App\Models\Semester;
use App\Models\StudentGroup;
use App\Models\Timeslot;
use App\Models\User;
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
        $teacher = User::create(['name' => 'Prof A', 'email' => 'profa@int.test', 'role' => 'prof', 'password' => bcrypt('password')]);
        $teacher2 = User::create(['name' => 'Prof B', 'email' => 'profb@int.test', 'role' => 'prof', 'password' => bcrypt('password')]);
        $subject = Module::create(['program_id' => $program->id, 'semester_id' => $semester->id, 'name' => 'Algorithmes', 'code' => 'ALG', 'weekly_hours' => 2]);
        $group = StudentGroup::create(['semester_id' => $semester->id, 'name' => 'G1', 'student_count' => 30, 'capacity' => 30]);
        $group2 = StudentGroup::create(['semester_id' => $semester->id, 'name' => 'G2', 'student_count' => 28, 'capacity' => 28]);
        $classroom = Classroom::create(['name' => 'A101', 'capacity' => 40, 'type' => 'classroom']);
        $classroom2 = Classroom::create(['name' => 'A102', 'capacity' => 40, 'type' => 'classroom']);
        $day = SchoolDay::create(['name' => 'Monday', 'position' => 1]);
        $timeslot = Timeslot::create(['name' => '08:00-10:00', 'starts_at' => '08:00', 'ends_at' => '10:00', 'position' => 1]);

        return compact('semester', 'subject', 'teacher', 'teacher2', 'group', 'group2', 'classroom', 'classroom2', 'day', 'timeslot');
    }

    public function test_teacher_double_booking_is_prevented_by_unique_constraint(): void
    {
        $data = $this->seedSemesterContext();
        TimetableSession::create([
            'module_id' => $data['subject']->id,
            'professor_id' => $data['teacher']->id,
            'classroom_id' => $data['classroom']->id,
            'student_group_id' => $data['group']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        TimetableSession::create([
            'module_id' => $data['subject']->id,
            'professor_id' => $data['teacher']->id,
            'classroom_id' => $data['classroom2']->id,
            'student_group_id' => $data['group2']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);
    }

    public function test_classroom_double_booking_is_prevented_by_unique_constraint(): void
    {
        $data = $this->seedSemesterContext();
        TimetableSession::create([
            'module_id' => $data['subject']->id,
            'professor_id' => $data['teacher']->id,
            'classroom_id' => $data['classroom']->id,
            'student_group_id' => $data['group']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        TimetableSession::create([
            'module_id' => $data['subject']->id,
            'professor_id' => $data['teacher2']->id,
            'classroom_id' => $data['classroom']->id,
            'student_group_id' => $data['group2']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);
    }

    public function test_group_double_booking_is_prevented_by_unique_constraint(): void
    {
        $data = $this->seedSemesterContext();
        TimetableSession::create([
            'module_id' => $data['subject']->id,
            'professor_id' => $data['teacher']->id,
            'classroom_id' => $data['classroom']->id,
            'student_group_id' => $data['group']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        TimetableSession::create([
            'module_id' => $data['subject']->id,
            'professor_id' => $data['teacher2']->id,
            'classroom_id' => $data['classroom2']->id,
            'student_group_id' => $data['group']->id,
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
            'module_id' => 999999,
            'professor_id' => $data['teacher']->id,
            'classroom_id' => $data['classroom']->id,
            'student_group_id' => $data['group']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);
    }

    public function test_legacy_columns_are_removed(): void
    {
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('timetable_sessions', 'subject_id'));
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('timetable_sessions', 'teacher_id'));
    }
}
