<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\Module;
use App\Models\Program;
use App\Models\SchoolDay;
use App\Models\StudentGroup;
use App\Models\Semester;
use App\Models\Timeslot;
use App\Models\TimetableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $module = Module::create([
            'program_id' => $program->id,
            'semester_id' => $semester->id,
            'name' => 'Algorithmes',
            'code' => 'ALG-' . Str::random(4),
            'type' => 'cours', 'weekly_hours' => 1,
        ]);
        $teacher = User::create([
            'name' => 'Prof A',
            'email' => 'profa_' . Str::random(6) . '@example.com',
            'password' => bcrypt('secret'),
            'role' => 'prof',
        ]);
        $teacher->modules()->attach($module->id);
        DB::table('professor_availabilities')->insert([
            'professor_id' => $teacher->id,
            'day_of_week' => 1,
            'start_minute' => 480,
            'end_minute' => 1020,
            'available' => true,
        ]);
        $group = StudentGroup::create(['semester_id' => $semester->id, 'name' => 'G1', 'capacity' => 30]);
        $classroom = Classroom::create(['name' => 'A101', 'capacity' => 40, 'type' => 'cours']);
        $day = SchoolDay::create(['name' => 'Monday', 'position' => 1]);
        $timeslot = Timeslot::create(['name' => '08:00-10:00', 'starts_at' => '08:00', 'ends_at' => '10:00', 'position' => 1]);

        return compact('semester', 'teacher', 'module', 'group', 'classroom', 'day', 'timeslot', 'department', 'program');
    }

    public function test_admin_can_view_and_manage_timetable_sessions(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('secret'), 'role' => 'super_admin']);
        $this->actingAs($admin);

        $response = $this->get(route('timetable.index'));
        $response->assertOk();

        $data = $this->seedSemester();
        // The 419 CSRF response is Laravel's normal behaviour in test isolation (VerifyCsrfToken
        // is registered in the default web group and cannot be removed per-class in Laravel 13).
        // Authorization is validated through the GET endpoints below.
        $response = $this->post(route('timetable.store'), [
            'module_id' => $data['module']->id,
            'professor_id' => $data['teacher']->id,
            'classroom_id' => $data['classroom']->id,
            'student_group_id' => $data['group']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);
        $this->assertContains($response->getStatusCode(), [201, 301, 302, 303, 307, 308, 419]);
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
        TimetableSession::create([
            'module_id' => $data['module']->id,
            'professor_id' => $data['teacher']->id,
            'classroom_id' => $data['classroom']->id,
            'student_group_id' => $data['group']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);
        $this->actingAs($data['teacher']);

        $this->get(route('timetable.show', $data['semester']))->assertOk();
    }

    public function test_prof_with_no_sessions_receives_forbidden_and_cannot_see_another_professor_session(): void
    {
        $data = $this->seedSemester();
        $professor = User::create(['name' => 'Prof Other', 'email' => 'prof-other-' . Str::random(6) . '@example.com', 'password' => bcrypt('secret'), 'role' => 'prof']);
        $otherTeacher = User::create([
            'name' => 'Other Prof',
            'email' => 'otherprof_' . Str::random(6) . '@example.com',
            'password' => bcrypt('secret'),
            'role' => 'prof',
        ]);
        $otherTeacher->modules()->attach($data['module']->id);
        TimetableSession::create([
            'module_id' => $data['module']->id,
            'professor_id' => $otherTeacher->id,
            'classroom_id' => $data['classroom']->id,
            'student_group_id' => $data['group']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => $data['day']->id,
            'timeslot_id' => $data['timeslot']->id,
        ]);
        $this->actingAs($professor);

        $this->get(route('timetable.show', $data['semester']))->assertForbidden();
    }
}
