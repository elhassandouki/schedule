<?php

namespace Tests\Unit;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Program;
use App\Models\SchoolDay;
use App\Models\StudentGroup;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Timeslot;
use App\Models\TimetableSession;
use App\Services\SessionConflictChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SessionConflictCheckerTest extends TestCase
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
        $subject = Subject::create([
            'teacher_id' => $teacher->id,
            'name' => 'Algorithmes',
            'code' => 'ALG',
            'sessions_per_week' => 1,
        ]);
        $group = StudentGroup::create(['semester_id' => $semester->id, 'name' => 'G1', 'capacity' => 30]);
        $classroom = Classroom::create(['name' => 'A101', 'capacity' => 40, 'type' => 'classroom']);
        $day = SchoolDay::create(['name' => 'Monday', 'position' => 1]);
        $timeslot = Timeslot::create(['name' => '08:00-10:00', 'starts_at' => '08:00', 'ends_at' => '10:00', 'position' => 1]);

        return compact('semester', 'teacher', 'subject', 'group', 'classroom', 'day', 'timeslot');
    }

    public function test_teacher_conflict_is_rejected(): void
    {
        $data = $this->seedSemesterContext();
        TimetableSession::create([
            'subject_id' => $data['subject']->id,
            'teacher_id' => $data['teacher']->id,
            'classroom_id' => $data['classroom']->id,
            'student_group_id' => $data['group']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);

        $checker = new SessionConflictChecker();

        try {
            $checker->validate([
                'semester_id' => $data['semester']->id,
                'subject_id' => $data['subject']->id,
                'teacher_id' => $data['teacher']->id,
                'classroom_id' => $data['classroom']->id,
                'student_group_id' => $data['group']->id,
                'day_id' => $data['day']->id,
                'timeslot_id' => $data['timeslot']->id,
            ]);
            $this->fail('Expected a validation exception for the teacher conflict.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('teacher_id', $exception->errors());
            $this->assertStringContainsString('Conflit professeur', $exception->errors()['teacher_id'][0]);
        }
    }

    public function test_classroom_conflict_is_rejected(): void
    {
        $data = $this->seedSemesterContext();
        TimetableSession::create([
            'subject_id' => $data['subject']->id,
            'teacher_id' => $data['teacher']->id,
            'classroom_id' => $data['classroom']->id,
            'student_group_id' => $data['group']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);

        $checker = new SessionConflictChecker();

        try {
            $checker->validate([
                'semester_id' => $data['semester']->id,
                'subject_id' => $data['subject']->id,
                'teacher_id' => $data['teacher']->id + 1,
                'classroom_id' => $data['classroom']->id,
                'student_group_id' => $data['group']->id + 1,
                'day_id' => $data['day']->id,
                'timeslot_id' => $data['timeslot']->id,
            ]);
            $this->fail('Expected a validation exception for the classroom conflict.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('classroom_id', $exception->errors());
            $this->assertStringContainsString('Conflit salle', $exception->errors()['classroom_id'][0]);
        }
    }

    public function test_group_conflict_is_rejected(): void
    {
        $data = $this->seedSemesterContext();
        TimetableSession::create([
            'subject_id' => $data['subject']->id,
            'teacher_id' => $data['teacher']->id,
            'classroom_id' => $data['classroom']->id,
            'student_group_id' => $data['group']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);

        $checker = new SessionConflictChecker();

        try {
            $checker->validate([
                'semester_id' => $data['semester']->id,
                'subject_id' => $data['subject']->id,
                'teacher_id' => $data['teacher']->id + 1,
                'classroom_id' => $data['classroom']->id + 1,
                'student_group_id' => $data['group']->id,
                'day_id' => $data['day']->id,
                'timeslot_id' => $data['timeslot']->id,
            ]);
            $this->fail('Expected a validation exception for the group conflict.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('student_group_id', $exception->errors());
            $this->assertStringContainsString('Conflit groupe', $exception->errors()['student_group_id'][0]);
        }
    }

    public function test_capacity_is_rejected_when_classroom_is_too_small_and_allowed_when_it_is_large_enough(): void
    {
        $data = $this->seedSemesterContext();
        $checker = new SessionConflictChecker();

        $smallClassroom = Classroom::create(['name' => 'A102', 'capacity' => 10, 'type' => 'classroom']);
        $largeClassroom = Classroom::create(['name' => 'A103', 'capacity' => 50, 'type' => 'classroom']);

        try {
            $checker->validate([
                'semester_id' => $data['semester']->id,
                'subject_id' => $data['subject']->id,
                'teacher_id' => $data['teacher']->id,
                'classroom_id' => $smallClassroom->id,
                'student_group_id' => $data['group']->id,
                'day_id' => $data['day']->id,
                'timeslot_id' => $data['timeslot']->id,
            ]);
            $this->fail('Expected a validation exception for insufficient classroom capacity.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('classroom_id', $exception->errors());
            $this->assertStringContainsString('capacity', $exception->errors()['classroom_id'][0]);
        }

        $checker->validate([
            'semester_id' => $data['semester']->id,
            'subject_id' => $data['subject']->id,
            'teacher_id' => $data['teacher']->id,
            'classroom_id' => $largeClassroom->id,
            'student_group_id' => $data['group']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);
        $this->assertTrue(true);
    }

    public function test_duplicate_subject_and_group_at_the_same_day_and_timeslot_is_rejected(): void
    {
        $data = $this->seedSemesterContext();
        TimetableSession::create([
            'subject_id' => $data['subject']->id,
            'teacher_id' => $data['teacher']->id,
            'classroom_id' => $data['classroom']->id,
            'student_group_id' => $data['group']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);

        $checker = new SessionConflictChecker();

        try {
            $checker->validate([
                'semester_id' => $data['semester']->id,
                'subject_id' => $data['subject']->id,
                'teacher_id' => $data['teacher']->id + 1,
                'classroom_id' => $data['classroom']->id + 1,
                'student_group_id' => $data['group']->id,
                'day_id' => $data['day']->id,
                'timeslot_id' => $data['timeslot']->id,
            ]);
            $this->fail('Expected a validation exception for the duplicate session.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('subject_id', $exception->errors());
            $this->assertStringContainsString('already scheduled', strtolower($exception->errors()['subject_id'][0]));
        }
    }

    public function test_valid_session_is_accepted(): void
    {
        $data = $this->seedSemesterContext();
        $checker = new SessionConflictChecker();

        $checker->validate([
            'semester_id' => $data['semester']->id,
            'subject_id' => $data['subject']->id,
            'teacher_id' => $data['teacher']->id,
            'classroom_id' => $data['classroom']->id,
            'student_group_id' => $data['group']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);

        $this->assertTrue(true);
    }
}
