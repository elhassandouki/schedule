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
use App\Services\AutoGenerateTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AutoGenerateTimetableTest extends TestCase
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
        $subject = Subject::create(['semester_id' => $semester->id, 'teacher_id' => $teacher->id, 'name' => 'Algorithmes', 'code' => 'ALG', 'sessions_per_week' => 2]);
        $section = Section::create(['program_id' => $program->id, 'name' => 'G1', 'capacity' => 30]);
        Classroom::create(['name' => 'A101', 'capacity' => 40, 'type' => 'classroom']);
        $day = SchoolDay::create(['name' => 'Monday', 'position' => 1]);
        $timeslot = Timeslot::create(['name' => '08:00-10:00', 'starts_at' => '08:00', 'ends_at' => '10:00', 'position' => 1]);
        $timeslot2 = Timeslot::create(['name' => '10:00-12:00', 'starts_at' => '10:00', 'ends_at' => '12:00', 'position' => 2]);

        return compact('semester', 'subject', 'section', 'day', 'timeslot', 'timeslot2');
    }

    public function test_generation_creates_timetable_sessions_and_not_timetable_entries(): void
    {
        $data = $this->seedSemesterContext();
        $report = (new AutoGenerateTimetable())->generate($data['semester']->id);

        $this->assertTrue($report['success']);
        $this->assertSame(2, DB::table('timetable_sessions')->where('semester_id', $data['semester']->id)->count());
        $this->assertSame(0, DB::table('timetable_entries')->count());
        $this->assertSame(2, $report['sessions_generated']);
    }

    public function test_generation_is_scoped_to_the_selected_semester_and_respects_existing_sessions(): void
    {
        $data = $this->seedSemesterContext();
        $otherSemester = Semester::create([
            'program_id' => $data['semester']->program_id,
            'academic_year_id' => $data['semester']->academic_year_id,
            'name' => 'S2',
            'number' => 2,
        ]);
        $otherSubject = Subject::create(['semester_id' => $otherSemester->id, 'teacher_id' => $data['subject']->teacher_id, 'name' => 'Physique', 'code' => 'PHY', 'sessions_per_week' => 1]);
        $otherSection = Section::create(['program_id' => $data['semester']->program_id, 'name' => 'G2', 'capacity' => 30]);
        DB::table('timetable_sessions')->insert([
            'subject_id' => $otherSubject->id,
            'teacher_id' => $data['subject']->teacher_id,
            'classroom_id' => 1,
            'section_id' => $otherSection->id,
            'semester_id' => $otherSemester->id,
            'day_id' => 1,
            'timeslot_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = (new AutoGenerateTimetable())->generate($data['semester']->id);

        $this->assertSame(2, DB::table('timetable_sessions')->where('semester_id', $data['semester']->id)->count());
        $this->assertSame(1, DB::table('timetable_sessions')->where('semester_id', $otherSemester->id)->count());
        $this->assertSame(2, $report['sessions_generated']);
    }

    public function test_generation_reports_skips_and_conflicts(): void
    {
        $data = $this->seedSemesterContext();
        $otherSubject = Subject::create(['semester_id' => $data['semester']->id, 'teacher_id' => $data['subject']->teacher_id, 'name' => 'Physique', 'code' => 'PHY', 'sessions_per_week' => 1]);
        $otherSection = Section::create(['program_id' => $data['semester']->program_id, 'name' => 'G2', 'capacity' => 30]);
        DB::table('timetable_sessions')->insert([
            'subject_id' => $data['subject']->id,
            'teacher_id' => $data['subject']->teacher_id,
            'classroom_id' => 1,
            'section_id' => $data['section']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => 1,
            'timeslot_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = (new AutoGenerateTimetable())->generate($data['semester']->id);

        $this->assertGreaterThanOrEqual(0, $report['sessions_skipped']);
        $this->assertIsArray($report['subjects']);
        $this->assertGreaterThanOrEqual(1, $report['sessions_generated']);
    }
}
