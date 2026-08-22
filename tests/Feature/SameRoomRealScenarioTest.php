<?php

namespace Tests\Feature;

use App\Models\{Classroom, StudentGroup, Timeslot, User};
use App\Services\AutoGenerateTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Reproduit un scénario proche de la base réelle du user (plusieurs modules,
 * plusieurs groupes) pour vérifier pourquoi un module changerait de salle
 * entre deux séances.
 */
class SameRoomRealScenarioTest extends TestCase
{
    use RefreshDatabase;

    private function dumpReport(array $report): void
    {
        echo "\nGenerated: {$report['sessions_generated']}, Skipped: {$report['sessions_skipped']}\n";
        foreach ($report['skipped_per_module'] as $mod => $reasons) {
            foreach ($reasons as $r) echo "SKIPPED[$mod]: $r\n";
        }
    }

    public function test_module_room_stable_with_multiple_modules_and_groups(): void
    {
        $now = now();
        DB::table('academic_years')->insert(['name' => '2026-2027', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('departments')->insert(['name' => 'Département Test', 'code' => 'DT', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('programs')->insert(['department_id' => 1, 'name' => 'PC', 'code' => 'PC', 'created_at' => $now, 'updated_at' => $now]);
        $sem = DB::table('semesters')->insertGetId(['program_id' => 1, 'number' => 1, 'name' => 'S1', 'weeks_count' => 1, 'academic_year_id' => 1, 'created_at' => $now, 'updated_at' => $now]);

        // Plusieurs modules comme en prod (3h chacun), avec profs distincts.
        $mods = [];
        foreach (['Mécanique', 'Thermodynamique', 'Atomistique'] as $i => $name) {
            $mods[$name] = DB::table('modules')->insertGetId(['program_id' => 1, 'semester_id' => $sem, 'name' => $name, 'code' => "M$i", 'type' => 'cours', 'weekly_hours' => 3, 'created_at' => $now, 'updated_at' => $now]);
            $prof = User::create(['name' => "Prof $name", 'email' => "p$i@example.com", 'password' => bcrypt('password'), 'role' => 'prof']);
            DB::table('professor_module')->insert(['professor_id' => $prof->id, 'module_id' => $mods[$name], 'created_at' => $now, 'updated_at' => $now]);
        }

        // 3 jours, créneaux 1h30 comme en prod.
        foreach ([['Monday', 1], ['Tuesday', 2], ['Wednesday', 3]] as [$d, $p]) {
            DB::table('days')->insert(['name' => $d, 'position' => $p, 'created_at' => $now, 'updated_at' => $now]);
        }
        Timeslot::create(['name' => '09:00-10:30', 'starts_at' => '09:00', 'ends_at' => '10:30', 'position' => 1]);
        Timeslot::create(['name' => '11:00-12:30', 'starts_at' => '11:00', 'ends_at' => '12:30', 'position' => 2]);
        Timeslot::create(['name' => '14:00-15:30', 'starts_at' => '14:00', 'ends_at' => '15:30', 'position' => 3]);
        Timeslot::create(['name' => '16:00-17:30', 'starts_at' => '16:00', 'ends_at' => '17:30', 'position' => 4]);

        StudentGroup::create(['semester_id' => $sem, 'name' => 'G1', 'capacity' => 30]);

        // 5 salles comme en prod (Bloc A salles...).
        Classroom::create(['name' => 'Bloc A - Salle 1', 'capacity' => 40, 'type' => 'cours']);
        Classroom::create(['name' => 'Bloc A - Salle 2', 'capacity' => 40, 'type' => 'cours']);
        Classroom::create(['name' => 'Bloc A - Salle 3', 'capacity' => 40, 'type' => 'cours']);
        Classroom::create(['name' => 'Amphi A', 'capacity' => 100, 'type' => 'amphitheatre']);
        Classroom::create(['name' => 'Labo Info', 'capacity' => 30, 'type' => 'labo']);

        $report = app(AutoGenerateTimetable::class)->generate($sem);
        $this->dumpReport($report);

        $sessions = DB::table('timetable_sessions as s')
            ->join('modules as m', 'm.id', '=', 's.module_id')
            ->join('classrooms as c', 'c.id', '=', 's.classroom_id')
            ->where('s.semester_id', $sem)
            ->select('m.name as module', 'c.name as salle', 's.day_id', 's.timeslot_id')
            ->orderBy('m.id')->orderBy('s.day_id')->orderBy('s.timeslot_id')->get();

        echo "\n";
        foreach ($sessions as $s) echo "{$s->module} | {$s->salle} | day {$s->day_id} slot {$s->timeslot_id}\n";

        $byModule = $sessions->groupBy('module');
        foreach ($byModule as $modName => $rows) {
            $rooms = $rows->pluck('salle')->unique()->values();
            echo "Module '$modName': " . count($rows) . " sessions, salles = [" . $rooms->implode(', ') . "]\n";
            $this->assertCount(1, $rooms, "Le module $modName doit garder la même salle pour toutes ses séances.");
        }
    }
}
