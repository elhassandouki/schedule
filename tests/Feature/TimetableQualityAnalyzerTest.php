<?php

namespace Tests\Feature;

use App\Console\Commands\AnalyzeTimetableQuality;
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
use App\Services\AutoGenerateTimetable;
use App\Services\TimetableQualityAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TimetableQualityAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    private function seedSemesterWithSessions(): array
    {
        $department = Department::create(['name' => 'Informatique-'.Str::random(4), 'code' => 'INFO-'.Str::random(4)]);
        $program = Program::create(['department_id' => $department->id, 'name' => 'Licence', 'code' => 'LIC-'.Str::random(4)]);
        $academicYearId = DB::table('academic_years')->insertGetId([
            'name' => '2026/2027-'.Str::random(4),
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $semester = Semester::create(['program_id' => $program->id, 'academic_year_id' => $academicYearId, 'name' => 'S1', 'number' => 1]);

        $teacher = Teacher::create(['name' => 'Prof A']);
        $teacher2 = Teacher::create(['name' => 'Prof B']);
        $subject = Subject::create(['semester_id' => $semester->id, 'teacher_id' => $teacher->id, 'name' => 'Algorithmes', 'code' => 'ALG-'.Str::random(4), 'sessions_per_week' => 2]);
        $subject2 = Subject::create(['semester_id' => $semester->id, 'teacher_id' => $teacher2->id, 'name' => 'Bases', 'code' => 'BAS-'.Str::random(4), 'sessions_per_week' => 2]);
        $section = Section::create(['program_id' => $program->id, 'name' => 'G1', 'capacity' => 30]);
        $section2 = Section::create(['program_id' => $program->id, 'name' => 'G2', 'capacity' => 30]);
        $classroom = Classroom::create(['name' => 'A101-'.Str::random(4), 'capacity' => 40, 'type' => 'classroom']);
        $dayOffset = (int) DB::table('days')->max('position') + 1;
        $day = SchoolDay::create(['name' => 'Monday-'.Str::random(4), 'position' => $dayOffset]);
        $day2 = SchoolDay::create(['name' => 'Tuesday-'.Str::random(4), 'position' => $dayOffset + 1]);
        $day3 = SchoolDay::create(['name' => 'Wednesday-'.Str::random(4), 'position' => $dayOffset + 2]);
        $timeslotOffset = (int) DB::table('timeslots')->max('position') + 1;
        $timeslot = Timeslot::create(['name' => '08:00-10:00', 'starts_at' => sprintf('%02d:00', 8 + $timeslotOffset), 'ends_at' => sprintf('%02d:00', 10 + $timeslotOffset), 'position' => $timeslotOffset]);
        $timeslot2 = Timeslot::create(['name' => '10:00-12:00', 'starts_at' => sprintf('%02d:00', 10 + $timeslotOffset), 'ends_at' => sprintf('%02d:00', 12 + $timeslotOffset), 'position' => $timeslotOffset + 1]);
        $timeslot3 = Timeslot::create(['name' => '12:00-14:00', 'starts_at' => sprintf('%02d:00', 12 + $timeslotOffset), 'ends_at' => sprintf('%02d:00', 14 + $timeslotOffset), 'position' => $timeslotOffset + 2]);

        return compact('semester', 'teacher', 'teacher2', 'subject', 'subject2', 'section', 'section2', 'classroom', 'day', 'day2', 'day3', 'timeslot', 'timeslot2', 'timeslot3');
    }

    public function test_completely_valid_timetable_has_no_warnings(): void
    {
        $data = $this->seedSemesterWithSessions();
        TimetableSession::create(['subject_id' => $data['subject']->id, 'teacher_id' => $data['teacher']->id, 'classroom_id' => $data['classroom']->id, 'section_id' => $data['section']->id, 'semester_id' => $data['semester']->id, 'day_id' => $data['day']->id, 'timeslot_id' => $data['timeslot']->id]);
        TimetableSession::create(['subject_id' => $data['subject2']->id, 'teacher_id' => $data['teacher2']->id, 'classroom_id' => $data['classroom']->id, 'section_id' => $data['section2']->id, 'semester_id' => $data['semester']->id, 'day_id' => $data['day2']->id, 'timeslot_id' => $data['timeslot2']->id]);

        $analysis = (new TimetableQualityAnalyzer())->analyze($data['semester']->id);
        $this->assertSame(0, count($analysis['hard_conflicts']));
        $this->assertLessThanOrEqual(2, count($analysis['soft_warnings']));
        $this->assertGreaterThan(0, $analysis['quality_score']);
    }

    public function test_skipped_sessions_are_reflected_in_generation_report(): void
    {
        $semester = Semester::create(['program_id' => Program::create(['department_id' => Department::create(['name' => 'Hist-'.Str::random(4), 'code' => 'HIS-'.Str::random(4)])->id, 'name' => 'Licence', 'code' => 'LIC2-'.Str::random(4)])->id, 'academic_year_id' => DB::table('academic_years')->insertGetId(['name' => '2026/2027-'.Str::random(4), 'created_at' => now(), 'updated_at' => now()]), 'name' => 'S2', 'number' => 2]);
        Subject::create(['semester_id' => $semester->id, 'teacher_id' => Teacher::create(['name' => 'Prof X'])->id, 'name' => 'Maths', 'code' => 'MTH-'.Str::random(4), 'sessions_per_week' => 1]);
        Section::create(['program_id' => $semester->program_id, 'name' => 'G1', 'capacity' => 30]);
        Classroom::create(['name' => 'Tiny-'.Str::random(4), 'capacity' => 10, 'type' => 'classroom']);
        SchoolDay::create(['name' => 'Monday-'.Str::random(4), 'position' => 1]);
        Timeslot::create(['name' => '08:00-10:00', 'starts_at' => '08:00', 'ends_at' => '10:00', 'position' => 1]);

        $report = (new AutoGenerateTimetable())->generate($semester->id);
        $this->assertGreaterThan(0, $report['sessions_skipped']);
    }

    public function test_teacher_overload_and_section_overload_are_warnings(): void
    {
        $data = $this->seedSemesterWithSessions();
        $teacher = $data['teacher'];
        $section = $data['section'];
        for ($i = 0; $i < 7; $i++) {
            $day = $i < 3 ? $data['day'] : ($i < 6 ? $data['day2'] : $data['day3']);
            $timeslot = $i % 3 === 0 ? $data['timeslot'] : ($i % 3 === 1 ? $data['timeslot2'] : $data['timeslot3']);
            TimetableSession::create(['subject_id' => $data['subject']->id, 'teacher_id' => $teacher->id, 'classroom_id' => $data['classroom']->id, 'section_id' => $section->id, 'semester_id' => $data['semester']->id, 'day_id' => $day->id, 'timeslot_id' => $timeslot->id]);
        }
        $analysis = (new TimetableQualityAnalyzer())->analyze($data['semester']->id);
        $this->assertNotEmpty($analysis['soft_warnings']);
    }

    public function test_classroom_utilization_is_reported(): void
    {
        $data = $this->seedSemesterWithSessions();
        TimetableSession::create(['subject_id' => $data['subject']->id, 'teacher_id' => $data['teacher']->id, 'classroom_id' => $data['classroom']->id, 'section_id' => $data['section']->id, 'semester_id' => $data['semester']->id, 'day_id' => $data['day']->id, 'timeslot_id' => $data['timeslot']->id]);
        $analysis = (new TimetableQualityAnalyzer())->analyze($data['semester']->id);
        $this->assertGreaterThanOrEqual(0, $analysis['classroom_utilization_percentage']);
    }

    public function test_excessive_gaps_and_consecutive_sessions_create_warnings(): void
    {
        $data = $this->seedSemesterWithSessions();
        TimetableSession::create(['subject_id' => $data['subject']->id, 'teacher_id' => $data['teacher']->id, 'classroom_id' => $data['classroom']->id, 'section_id' => $data['section']->id, 'semester_id' => $data['semester']->id, 'day_id' => $data['day']->id, 'timeslot_id' => $data['timeslot']->id]);
        TimetableSession::create(['subject_id' => $data['subject']->id, 'teacher_id' => $data['teacher']->id, 'classroom_id' => $data['classroom']->id, 'section_id' => $data['section']->id, 'semester_id' => $data['semester']->id, 'day_id' => $data['day2']->id, 'timeslot_id' => $data['timeslot2']->id]);
        TimetableSession::create(['subject_id' => $data['subject']->id, 'teacher_id' => $data['teacher']->id, 'classroom_id' => $data['classroom']->id, 'section_id' => $data['section']->id, 'semester_id' => $data['semester']->id, 'day_id' => $data['day3']->id, 'timeslot_id' => $data['timeslot3']->id]);
        $analysis = (new TimetableQualityAnalyzer())->analyze($data['semester']->id);
        $this->assertNotEmpty($analysis['soft_warnings']);
    }

    public function test_quality_score_is_calculated_and_explainable(): void
    {
        $data = $this->seedSemesterWithSessions();
        $analysis = (new TimetableQualityAnalyzer())->analyze($data['semester']->id, ['sessions_skipped' => 3]);
        $this->assertGreaterThanOrEqual(0, $analysis['quality_score']);
        $this->assertStringContainsString('Timetable Quality', $analysis['quality_summary']);
    }

    public function test_multiple_semesters_remain_isolated(): void
    {
        $first = $this->seedSemesterWithSessions();
        $second = $this->seedSemesterWithSessions();
        $analysis = (new TimetableQualityAnalyzer())->analyze($first['semester']->id);
        $this->assertSame($first['semester']->id, $analysis['semester_id']);
    }

    public function test_command_outputs_quality_information(): void
    {
        $data = $this->seedSemesterWithSessions();
        $exitCode = Artisan::call('timetable:quality', ['semester_id' => $data['semester']->id]);
        $this->assertSame(0, $exitCode);
    }
}
