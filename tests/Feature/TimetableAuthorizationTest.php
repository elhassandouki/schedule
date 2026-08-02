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

class TimetableAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function seedSemester(): array
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

        return compact('semester', 'teacher', 'subject', 'section', 'classroom', 'day', 'timeslot', 'department', 'program');
    }

    public function test_admin_can_view_and_manage_timetable_sessions(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('secret'), 'role' => 'super_admin']);
        $this->actingAs($admin);

        $response = $this->get(route('timetable.index'));
        $response->assertOk();

        $data = $this->seedSemester();
        $response = $this->post(route('timetable.store'), [
            'subject_id' => $data['subject']->id,
            'teacher_id' => $data['teacher']->id,
            'classroom_id' => $data['classroom']->id,
            'section_id' => $data['section']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);

        $response->assertRedirect(route('timetable.index'));
        $this->assertDatabaseHas('timetable_sessions', ['semester_id' => $data['semester']->id]);
    }

    public function test_chef_departement_can_access_authorized_department_data(): void
    {
        $data = $this->seedSemester();
        $chef = User::create(['name' => 'Chef Departement', 'email' => 'chef-dept@example.com', 'password' => bcrypt('secret'), 'role' => 'chef_departement', 'department_id' => $data['department']->id]);
        $this->actingAs($chef);

        $this->get(route('timetable.show', $data['semester']))->assertOk();
    }

    public function test_chef_filiere_can_access_authorized_program_data(): void
    {
        $data = $this->seedSemester();
        $chef = User::create(['name' => 'Chef Filiere', 'email' => 'chef-filiere@example.com', 'password' => bcrypt('secret'), 'role' => 'chef_filiere', 'program_id' => $data['program']->id]);
        $this->actingAs($chef);

        $this->get(route('timetable.show', $data['semester']))->assertOk();
    }

    public function test_prof_can_only_see_their_own_timetable_sessions(): void
    {
        $data = $this->seedSemester();
        $professor = User::create(['name' => 'Prof Own', 'email' => 'prof-own@example.com', 'password' => bcrypt('secret'), 'role' => 'prof']);
        $teacher = Teacher::create(['name' => 'Prof Own', 'user_id' => $professor->id]);
        TimetableSession::create([
            'subject_id' => $data['subject']->id,
            'teacher_id' => $teacher->id,
            'classroom_id' => $data['classroom']->id,
            'section_id' => $data['section']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);
        $this->actingAs($professor);

        $this->get(route('timetable.show', $data['semester']))->assertOk();
    }

    public function test_prof_with_no_sessions_receives_forbidden_and_cannot_see_another_professor_session(): void
    {
        $data = $this->seedSemester();
        $professor = User::create(['name' => 'Prof Other', 'email' => 'prof-other@example.com', 'password' => bcrypt('secret'), 'role' => 'prof']);
        $otherTeacher = Teacher::create(['name' => 'Other Prof']);
        TimetableSession::create([
            'subject_id' => $data['subject']->id,
            'teacher_id' => $otherTeacher->id,
            'classroom_id' => $data['classroom']->id,
            'section_id' => $data['section']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);
        $this->actingAs($professor);

        $this->get(route('timetable.show', $data['semester']))->assertForbidden();
    }
}
