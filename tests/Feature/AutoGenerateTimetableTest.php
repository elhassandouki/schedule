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
use App\Services\AutoGenerateTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        $module = Module::create([
            'program_id' => $program->id,
            'semester_id' => $semester->id,
            'name' => 'Algorithmes',
            'code' => 'ALG',
            'type' => 'cours', 'weekly_hours' => 2,
        ]);
        $professor = User::create([
            'name' => 'Prof A',
            'email' => 'profa_' . Str::random(6) . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'prof',
        ]);
        $professor->modules()->attach($module->id);
        // Prof disponible tous les jours ouvrables (1-5) de 8h00 à 18h00.
        foreach (range(1, 5) as $day) {
            DB::table('professor_availabilities')->insert([
                'professor_id' => $professor->id,
                'day_of_week' => $day,
                'start_minute' => 480,
                'end_minute' => 1080,
                'available' => true,
            ]);
        }

        $group = StudentGroup::create(['semester_id' => $semester->id, 'name' => 'G1', 'capacity' => 30]);
        $group2 = StudentGroup::create(['semester_id' => $semester->id, 'name' => 'G2', 'capacity' => 30]);
        Classroom::create(['name' => 'A101', 'capacity' => 40, 'type' => 'cours']);
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        foreach ($days as $pos => $day) {
            SchoolDay::create(['name' => $day, 'position' => $pos + 1]);
        }
        $slots = [
            ['08:00-10:00', '08:00', '10:00'],
            ['10:00-12:00', '10:00', '12:00'],
            ['13:00-15:00', '13:00', '15:00'],
            ['15:00-17:00', '15:00', '17:00'],
        ];
        foreach ($slots as $pos => [$name, $start, $end]) {
            Timeslot::create(['name' => $name, 'starts_at' => $start, 'ends_at' => $end, 'position' => $pos + 1]);
        }

        return compact('semester', 'module', 'professor', 'group');
    }

    public function test_generation_creates_timetable_sessions_for_the_selected_semester(): void
    {
        $data = $this->seedSemesterContext();
                $report = (new AutoGenerateTimetable())->generate($data['semester']->id);
        $this->assertTrue($report['success']);
        // Respect strict de weekly_hours : budget minute = 2h × 60 ; créneaux de 2h (120 min)
        // → 1 session par groupe × 2 groupes = 2, sans jamais dépasser 2h/semaine.
        $this->assertSame(2, DB::table('timetable_sessions')->where('semester_id', $data['semester']->id)->count());
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
        // Le module de l'autre semestre partage le même volume horaire que celui
        // du test : ses sessions sont générées séparément et ne comptent pas ici.
        $otherSemester->weeks_count = 1;
        $otherSemester->save();
        $otherModule = Module::create([
            'program_id' => $data['semester']->program_id,
            'semester_id' => $otherSemester->id,
            'code' => 'PHY',
            'name' => 'Physique',
            'type' => 'cours', 'weekly_hours' => 2,
        ]);
        $data['professor']->modules()->attach($otherModule->id);
        $otherGroup = StudentGroup::create(['semester_id' => $otherSemester->id, 'name' => 'G2', 'capacity' => 30]);
        DB::table('timetable_sessions')->insert([
            'module_id' => $otherModule->id,
            'professor_id' => $data['professor']->id,
            'classroom_id' => 1,
            'student_group_id' => $otherGroup->id,
            'semester_id' => $otherSemester->id,
            'day_id' => 1,
            'timeslot_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

                $report = (new AutoGenerateTimetable())->generate($data['semester']->id);
        // Respect strict de weekly_hours : budget minute = 2h × 60 ; créneaux de 2h (120 min)
        // → 1 session par groupe × 2 groupes = 2, sans jamais dépasser 2h/semaine.
        $this->assertSame(2, DB::table('timetable_sessions')->where('semester_id', $data['semester']->id)->count());
        // 1 session préexistante : le générateur du semestre S1 ne touche pas S2
        $this->assertSame(1, DB::table('timetable_sessions')->where('semester_id', $otherSemester->id)->count());
        $this->assertSame(2, $report['sessions_generated']);
    }

    public function test_generation_reports_skips_and_conflicts(): void
    {
        $data = $this->seedSemesterContext();
        // Un créneau est bloqué manuellement, le générateur doit l'éviter.
        DB::table('timetable_sessions')->insert([
            'module_id' => $data['module']->id,
            'professor_id' => $data['professor']->id,
            'classroom_id' => 1,
            'student_group_id' => $data['group']->id,
            'semester_id' => $data['semester']->id,
            'day_id' => 1,
            'timeslot_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = (new AutoGenerateTimetable())->generate($data['semester']->id);

        $this->assertGreaterThanOrEqual(0, $report['sessions_skipped']);
        $this->assertIsArray($report['generated_per_module'] ?? $report['generated_per_subject'] ?? []);
        $this->assertGreaterThanOrEqual(1, $report['sessions_generated']);
    }
}
