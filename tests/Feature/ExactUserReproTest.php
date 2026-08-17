<?php

namespace Tests\Feature;

use App\Models\{Classroom, StudentGroup, Timeslot, User};
use App\Services\AutoGenerateTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Reproduction EXACTE de la structure du PDF de l'utilisateur :
 * 8 modules de 3h/semaine, UN SEUL prof pour tous les modules (PR3),
 * 1 seul groupe, 4 créneaux de 1h30 par jour (Lun-Sam), ~12 salles.
 * Le PDF de l'utilisateur montre "Biologie cellulaire" en Salle 02 au créneau
 * 09:00 puis Salle 03 au créneau 11:00 le lundi → bug de stabilité.
 */
class ExactUserReproTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_user_structure_room_stability(): void
    {
        $now = now();
        DB::table('academic_years')->insert(['name' => '2026-2027', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('departments')->insert(['name' => 'SVT', 'code' => 'SVT', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('programs')->insert(['department_id' => 1, 'name' => 'SVT', 'code' => 'SVT', 'created_at' => $now, 'updated_at' => $now]);
        $sem = DB::table('semesters')->insertGetId(['program_id' => 1, 'number' => 1, 'name' => 'S1', 'weeks_count' => 15, 'academic_year_id' => 1, 'created_at' => $now, 'updated_at' => $now]);

        $prof = User::create(['name' => 'Prof PR3', 'email' => 'pr3@example.com', 'password' => bcrypt('password'), 'role' => 'prof']);

        foreach (['Biologie cellulaire','Histologie et embryologie','Géologie générale','Mathématiques','Physique','Chimie','Zoologie','Botanique'] as $i => $m) {
            $mid = DB::table('modules')->insertGetId(['program_id' => 1, 'semester_id' => $sem, 'name' => $m, 'code' => 'M'.($i+1), 'weekly_hours' => 3, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('professor_module')->insert(['professor_id' => $prof->id, 'module_id' => $mid, 'created_at' => $now, 'updated_at' => $now]);
        }

        foreach ([['Monday',1],['Tuesday',2],['Wednesday',3],['Thursday',4],['Friday',5],['Saturday',6]] as [$n,$p]) {
            DB::table('days')->insert(['name' => $n, 'position' => $p, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ([['09:00-10:30','09:00','10:30',1],['11:00-12:30','11:00','12:30',2],['14:00-15:30','14:00','15:30',3],['16:00-17:30','16:00','17:30',4]] as [$n,$s,$e,$p]) {
            Timeslot::create(['name' => $n, 'starts_at' => $s, 'ends_at' => $e, 'position' => $p]);
        }

        StudentGroup::create(['semester_id' => $sem, 'name' => 'S1_SVT_Groupe 1', 'capacity' => 35]);

        for ($i = 2; $i <= 13; $i++) {
            Classroom::create(['name' => "Bloc A - Salle " . str_pad($i, 2, '0', STR_PAD_LEFT), 'capacity' => 40, 'type' => 'classroom']);
        }

        $report = app(AutoGenerateTimetable::class)->generate($sem);
        echo "\nReport: generated={$report['sessions_generated']}, skipped={$report['sessions_skipped']}\n";
        echo "Skipped per module: ".json_encode($report['skipped_per_module'] ?? [], JSON_UNESCAPED_UNICODE)."\n";

        $rows = DB::table('timetable_sessions as s')
            ->join('modules as m', 'm.id', '=', 's.module_id')
            ->join('classrooms as c', 'c.id', '=', 's.classroom_id')
            ->join('days as d', 'd.id', '=', 's.day_id')
            ->join('timeslots as t', 't.id', '=', 's.timeslot_id')
            ->where('s.semester_id', $sem)
            ->select('m.name as module', 'c.name as salle', 'd.position as jour', 't.position as creneau')
            ->orderBy('m.id')->orderBy('jour')->orderBy('creneau')->get();

        echo "\nResultat:\n";
        foreach ($rows as $r) echo "{$r->module} | {$r->salle} | jour {$r->jour} creneau {$r->creneau}\n";

        foreach ($rows->groupBy('module') as $modName => $r) {
            $this->assertCount(1, $r->pluck('salle')->unique(), "Module '$modName' doit garder la même salle.");
        }
    }
}
