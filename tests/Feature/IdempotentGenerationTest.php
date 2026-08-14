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
use App\Models\User;
use App\Services\AutoGenerateTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdempotentGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regeneration_does_not_exceed_the_module_weekly_quota(): void
    {
        $department = Department::create(['name' => 'PC', 'code' => 'PC']);
        $program = Program::create(['department_id' => $department->id, 'name' => 'PC', 'code' => 'PC']);
        $academicYear = DB::table('academic_years')->insertGetId([
            'name' => '2026/2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-06-30',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $semester = Semester::create([
            'program_id' => $program->id,
            'academic_year_id' => $academicYear,
            'name' => 'S1',
            'number' => 1,
        ]);

        $module = Module::create([
            'program_id' => $program->id,
            'semester_id' => $semester->id,
            'name' => 'Mécanique',
            'code' => 'MEC',
            'weekly_hours' => 3,
        ]);

        $professor = User::create([
            'name' => 'Prof EN2',
            'email' => 'prof_' . Str::random(6) . '@example.com',
            'password' => bcrypt('secret'),
            'role' => 'prof',
        ]);
        $professor->modules()->attach($module->id);

        // Le professeur est disponible tous les jours ouvrables (comme en production).
        foreach (range(1, 6) as $day) {
            DB::table('professor_availabilities')->insert([
                'professor_id' => $professor->id,
                'day_of_week' => $day,
                'start_minute' => 480,
                'end_minute' => 1020,
                'available' => true,
            ]);
        }

        $group = StudentGroup::create(['semester_id' => $semester->id, 'name' => 'PC_S1_G1', 'capacity' => 70]);
        Classroom::create(['name' => 'Salle 01', 'capacity' => 80, 'type' => 'classroom']);

        $days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        foreach ($days as $pos => $day) {
            SchoolDay::create(['name' => $day, 'position' => $pos + 1]);
        }
        $slots = [
            ['09:00-10:30', '09:00', '10:30'],
            ['11:00-12:30', '11:00', '12:30'],
            ['14:00-15:30', '14:00', '15:30'],
            ['16:00-17:30', '16:00', '17:30'],
        ];
        foreach ($slots as $pos => [$name, $start, $end]) {
            Timeslot::create(['name' => $name, 'starts_at' => $start, 'ends_at' => $end, 'position' => $pos + 1]);
        }

        // Première génération : module 3h/semaine avec créneaux de 1h30 → 2 sessions.
        $report1 = (new AutoGenerateTimetable())->generate($semester->id);
        $this->assertTrue($report1['success']);
        $this->assertSame(2, DB::table('timetable_sessions')
            ->where('module_id', $module->id)
            ->where('student_group_id', $group->id)
            ->where('semester_id', $semester->id)
            ->count());

        // Relance de la génération sans suppression : aucune session supplémentaire.
        $report2 = (new AutoGenerateTimetable())->generate($semester->id);
        $this->assertTrue($report2['success']);
        $this->assertSame(0, $report2['sessions_generated']);
        $this->assertSame(2, DB::table('timetable_sessions')
            ->where('module_id', $module->id)
            ->where('student_group_id', $group->id)
            ->where('semester_id', $semester->id)
            ->count());
    }
}
