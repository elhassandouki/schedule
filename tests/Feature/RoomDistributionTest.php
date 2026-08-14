<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Services\AutoGenerateTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prouve que le générateur répartit les sessions sur plusieurs salles (au lieu
 * de rester collé sur une seule) : chaque placement choisit la salle libre du
 * créneau avec le moins de sessions placées, puis la plus petite capacité
 * suffisante pour le groupe.
 */
class RoomDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sessions_are_distributed_across_free_rooms(): void
    {
        $this->seed(\Database\Seeders\UnifiedDemoSeeder::class);
        $now = now();

        // Le semestre 1 du demo (5 modules, profs, créneaux 2h) reste intact.
        // On ajoute une 3e salle libre et suffisante : il y a déjà 2 salles du demo.
        Classroom::create(['name' => 'Salle 3', 'capacity' => 40, 'type' => 'classroom']);

        // Nettoyer les sessions pour partir d'une génération propre.
        DB::table('timetable_sessions')->delete();

        $report = app(AutoGenerateTimetable::class)->generate(1);

        $this->assertTrue($report['success'], json_encode($report['skipped_per_module']));
        $this->assertGreaterThan(0, $report['sessions_generated']);

        // Répartition : aucune salle ne doit concentrer plus de la moitié des sessions
        // et au moins 4 salles doivent être utilisées (2 du demo + Salle 3 ajoutée, plus
        // Amphi A dont la grande capacité le rend éligible à tous les groupes).
        $counts = DB::table('timetable_sessions')
            ->join('classrooms', 'classrooms.id', '=', 'timetable_sessions.classroom_id')
            ->where('semester_id', 1)
            ->groupBy('classroom_id')->selectRaw('COUNT(*) as total')->pluck('total')->all();
        $total = array_sum($counts);
        foreach ($counts as $count) {
            $this->assertLessThanOrEqual((int) ceil($total / 2), $count,
                'Une salle concentre trop de sessions : la répartition a échoué.');
        }
        $this->assertGreaterThanOrEqual(4, count($counts),
            'Les sessions n\'ont pas été réparties sur toutes les salles libres.');
    }
}
