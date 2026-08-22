<?php

namespace Tests\Feature;

use App\Models\{ScheduleHistory, TimetableSession, User};
use App\Services\AutoGenerateTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Reproduction du bug rapporté par l'utilisateur :
 * 1. Une génération crée des sessions (ancien code, salles instables).
 * 2. Le générateur actuel trouve le quota déjà atteint pour les modules
 *    concernés et ne replace que les autres → le PDF continue d'afficher
 *    l'ancien emploi (salles instables).
 * 3. L'ancien bouton "Supprimer" ne supprimait que les sessions créées
 *    exactement à l'horodatage de l'histoire de génération → les sessions
 *    résiduelles restaient en base.
 * 4. Corrigé : supprimer TOUTES les sessions du semestre, puis régénérer.
 */
class DestroyRegenerateFlowTest extends TestCase
{
    use RefreshDatabase;

    private function setupScenario(): array
    {
        $now = now();
        DB::table('academic_years')->insert(['name' => '2026-2027', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('departments')->insert(['name' => 'SVT', 'code' => 'SVT', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('programs')->insert(['department_id' => 1, 'name' => 'SVT', 'code' => 'SVT', 'created_at' => $now, 'updated_at' => $now]);
        $sem = DB::table('semesters')->insertGetId(['program_id' => 1, 'number' => 1, 'name' => 'S1', 'weeks_count' => 15, 'academic_year_id' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('student_groups')->insert(['semester_id' => $sem, 'name' => 'G1', 'capacity' => 35, 'created_at' => $now, 'updated_at' => $now]);
        $prof = User::create(['name' => 'PR3', 'email' => 'pr3@example.com', 'password' => bcrypt('password'), 'role' => 'prof']);

        // 2 modules : Biologie (avec sessions résiduelles) et Chimie (propre).
        $bioId = DB::table('modules')->insertGetId(['program_id' => 1, 'semester_id' => $sem, 'name' => 'Biologie cellulaire', 'code' => 'M1', 'type' => 'cours', 'weekly_hours' => 3, 'created_at' => $now, 'updated_at' => $now]);
        $chiId = DB::table('modules')->insertGetId(['program_id' => 1, 'semester_id' => $sem, 'name' => 'Chimie', 'code' => 'M2', 'type' => 'cours', 'weekly_hours' => 3, 'created_at' => $now, 'updated_at' => $now]);
        foreach ([$bioId, $chiId] as $m) {
            DB::table('professor_module')->insert(['professor_id' => $prof->id, 'module_id' => $m, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (range(1, 6) as $d) {
            DB::table('professor_availabilities')->insert(['professor_id' => $prof->id, 'day_of_week' => $d, 'available' => true, 'start_minute' => 0, 'end_minute' => 1440, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ([['Monday', 1], ['Tuesday', 2]] as [$n, $p]) {
            DB::table('days')->insert(['name' => $n, 'position' => $p, 'created_at' => $now, 'updated_at' => $now]);
        }
        \App\Models\Timeslot::create(['name' => '09:00-10:30', 'starts_at' => '09:00', 'ends_at' => '10:30', 'position' => 1]);
        \App\Models\Timeslot::create(['name' => '11:00-12:30', 'starts_at' => '11:00', 'ends_at' => '12:30', 'position' => 2]);
        \App\Models\Classroom::create(['name' => 'Salle 01', 'capacity' => 40, 'type' => 'cours']);
        \App\Models\Classroom::create(['name' => 'Salle 02', 'capacity' => 40, 'type' => 'cours']);

        return ['sem' => $sem, 'bioId' => $bioId, 'chiId' => $chiId, 'profId' => $prof->id, 'groupId' => 1];
    }

    public function test_residual_sessions_block_regeneration_and_old_destroy_logic_misses_them(): void
    {
        $ctx = $this->setupScenario();
        [$sem, $bioId, $chiId, $profId, $groupId] = array_values($ctx);

        // 1. Sessions "résiduelles" d'une ancienne génération (salles
        //    instables) créées à un horodatage DIFFÉRENT de toute histoire.
        $staleDate = now()->subHour()->format('Y-m-d H:i:s');
        DB::table('timetable_sessions')->insert([
            ['module_id' => $bioId, 'professor_id' => $profId, 'semester_id' => $sem,
                'student_group_id' => $groupId, 'classroom_id' => 1, 'day_id' => 1, 'timeslot_id' => 1,
                'created_at' => $staleDate, 'updated_at' => $staleDate],
            ['module_id' => $bioId, 'professor_id' => $profId, 'semester_id' => $sem,
                'student_group_id' => $groupId, 'classroom_id' => 2, 'day_id' => 1, 'timeslot_id' => 2,
                'created_at' => $staleDate, 'updated_at' => $staleDate],
        ]);

        // 2. Le générateur trouve que Biologie a déjà 180 min (quota 3h) et ne
        //    replace que Chimie → le PDF continue d'afficher les 2 sessions
        //    Biologie résiduelles dans 2 salles différentes.
        $report = app(AutoGenerateTimetable::class)->generate($sem);
        $this->assertEquals(2, $report['sessions_generated'],
            'Seuls les modules sans sessions résiduelles sont remplacés : l\'ancien emploi Biologie persiste.');

        // Simuler l'histoire de génération telle que le contrôleur la crée
        // (le service n'en crée pas ; c'est le contrôleur qui l'insère).
        $history = ScheduleHistory::create([
            'semester_id' => $sem,
            'name' => 'Proposition 18/08/2026 15:00',
            'status' => 'partial',
            'generated_sessions_count' => 2,
            'skipped_sessions_count' => 1,
            'generated_by_user_id' => $profId,
        ]);

        $bioSessions = DB::table('timetable_sessions')->where('module_id', $bioId)->where('semester_id', $sem)->get();
        $this->assertCount(2, $bioSessions->pluck('classroom_id')->unique(),
            'Biologie garde ses 2 sessions instables (Salle 01 + Salle 02) dans le PDF.');
        $this->assertNotNull($history, 'Une histoire de génération doit exister.');

        // 3. Ancienne logique de suppression (filtrage par horodatage) : elle
        //    cible uniquement les sessions créées dans [created_at, updated_at]
        //    de l'histoire → les sessions résiduelles échappent.
        // Le filtre par horodatage supprime les 2 sessions de Chimie (créées
        // au timestamp de l'histoire) mais RATE les 2 sessions résiduelles de
        // Biologie (créées une heure avant) — exactement le bug de l'utilisateur.
        $oldDeleted = DB::table('timetable_sessions')->where('semester_id', $sem)
            ->where('created_at', '>=', $history->created_at)
            ->where('created_at', '<=', $history->updated_at)->delete();
        $residualBio = DB::table('timetable_sessions')->where('semester_id', $sem)
            ->where('module_id', $bioId)->count();
        $this->assertEquals(2, $oldDeleted,
            'L\'ancienne logique ne supprime que les sessions créées au timestamp de l\'histoire.');
        $this->assertEquals(2, $residualBio,
            'Bug confirmé : les 2 sessions Biologie résiduelles (salles instables) échappent au filtre.');

        // 4. Nouvelle logique du contrôleur : supprimer TOUTES les sessions
        //    du semestre lié à l'histoire.
        $deleted = TimetableSession::where('semester_id', $sem)->delete();
        $this->assertEquals(2, $deleted, 'La nouvelle logique supprime les 2 sessions Biologie résiduelles que l\'ancienne logique a ratées.');
        $this->assertEquals(0, DB::table('timetable_sessions')->where('semester_id', $sem)->count());

        // 5. Régénération propre : 4 sessions placées, salles stables.
        $report2 = app(AutoGenerateTimetable::class)->generate($sem);
        $this->assertEquals(4, $report2['sessions_generated']);

        $rows = DB::table('timetable_sessions')->where('semester_id', $sem)->get();
        foreach ($rows->groupBy('module_id') as $moduleId => $modSessions) {
            $this->assertCount(1, $modSessions->pluck('classroom_id')->unique(),
                "Le module {$moduleId} doit garder la même salle pour toutes ses séances.");
        }
    }
}
