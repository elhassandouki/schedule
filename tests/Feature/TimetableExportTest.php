<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Semester;
use App\Models\StudentGroup;
use App\Models\TimetableSession;
use App\Models\User;
use App\Services\TimetableExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimetableExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin-export@test.local',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $department = DB::table('departments')->insertGetId(['name' => 'Département Info', 'code' => 'INFO', 'created_at' => now(), 'updated_at' => now()]);
        $program = DB::table('programs')->insertGetId(['name' => 'Licence Informatique', 'code' => 'LIC-INFO', 'department_id' => $department, 'created_at' => now(), 'updated_at' => now()]);
        $year = DB::table('academic_years')->insertGetId(['name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->semester = Semester::create([
            'name' => 'Semestre 1',
            'number' => 1,
            'program_id' => $program,
            'academic_year_id' => $year,
        ]);

        $this->seedReferenceData($program);
    }

    private function seedReferenceData(int $programId): void
    {
        $day = DB::table('days')->insertGetId(['name' => 'Monday', 'position' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $slot = DB::table('timeslots')->insertGetId(['name' => '08:00-10:00', 'position' => 1, 'starts_at' => '08:00:00', 'ends_at' => '10:00:00', 'created_at' => now(), 'updated_at' => now()]);
        $prof = User::create(['name' => 'Prof Export', 'email' => 'prof-export@test.local', 'password' => bcrypt('password'), 'role' => 'prof']);
        $module = Module::create(['name' => 'Module Export', 'code' => 'MOD-EXP', 'weekly_hours' => 2, 'program_id' => $programId, 'semester_id' => $this->semester->id]);
        $group = StudentGroup::create(['name' => 'Groupe Export', 'semester_id' => $this->semester->id, 'program_id' => $programId]);
        $room = DB::table('classrooms')->insertGetId(['name' => 'Salle Export', 'capacity' => 30, 'created_at' => now(), 'updated_at' => now()]);

        TimetableSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $module->id,
            'student_group_id' => $group->id,
            'professor_id' => $prof->id,
            'classroom_id' => $room,
            'day_id' => $day,
            'timeslot_id' => $slot,
        ]);
    }

    public function test_export_pdf_requires_auth()
    {
        $this->get('/timetable/' . $this->semester->id . '/export-pdf')->assertRedirect('/login');
    }

    public function test_export_pdf_returns_pdf_for_admin()
    {
        $this->actingAs($this->admin)
            ->get('/timetable/' . $this->semester->id . '/export-pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_export_excel_returns_xlsx_for_admin()
    {
        $this->actingAs($this->admin)
            ->get('/timetable/' . $this->semester->id . '/export-excel')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_export_fails_gracefully_without_sessions()
    {
        TimetableSession::query()->delete();

        $this->actingAs($this->admin)
            ->get('/timetable/' . $this->semester->id . '/export-pdf')
            ->assertRedirect()
            ->assertSessionHasErrors('export');
    }

    public function test_professors_can_export_only_their_own_semesters()
    {
        $prof = User::where('email', 'prof-export@test.local')->first();
        $otherSemester = Semester::create([
            'name' => 'Semestre 2',
            'number' => 2,
            'program_id' => $this->semester->program_id,
            'academic_year_id' => $this->semester->academic_year_id,
        ]);

        $this->actingAs($prof)
            ->get('/timetable/' . $otherSemester->id . '/export-pdf')
            ->assertForbidden();
    }

    public function test_day_names_are_translated_to_french()
    {
        $service = app(TimetableExportService::class);
        $data = $service->collect($this->semester, $this->admin);

        $this->assertNotEmpty($data['entries']);
        $this->assertSame('Lundi', $service->translateDay('Monday'));
        $this->assertSame('Vendredi', $service->translateDay('Friday'));
    }
}
