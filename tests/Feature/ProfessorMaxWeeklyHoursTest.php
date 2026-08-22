<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\StudentGroup;
use App\Models\Timeslot;
use App\Models\User;
use App\Services\AutoGenerateTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Le générateur respecte la contrainte max_weekly_hours des professeurs :
 * aucun créneau ne doit faire dépasser au professeur son plafond horaire
 * hebdomadaire défini dans users.max_weekly_hours.
 */
class ProfessorMaxWeeklyHoursTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Retourne les minutes déjà posées par un prof dans le semestre donné.
     */
    private function profMinutes(int $professorId, int $semesterId): int
    {
        return (int) (DB::table('timetable_sessions as ts')
            ->join('timeslots as t', 't.id', '=', 'ts.timeslot_id')
            ->where('ts.professor_id', $professorId)
            ->where('ts.semester_id', $semesterId)
            ->selectRaw(DB::getDriverName() === 'sqlite'
                ? 'ROUND(SUM((julianday(t.ends_at) - julianday(t.starts_at)) * 1440)) as total'
                : 'SUM(TIME_TO_SEC(TIMEDIFF(t.ends_at, t.starts_at))) / 60 as total')
            ->value('total'));
    }

    /** Scénario de base : 3 modules de 3h par prof plafonné + 1 module par prof sans plafond. */
    private function seedScenario(int $profMaxHours): array
    {
        $now = now();
        DB::table('academic_years')->insert(['name' => '2026-2027', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('departments')->insert(['name' => 'Département test', 'code' => 'DT', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('programs')->insert(['department_id' => 1, 'name' => 'Filière test', 'code' => 'FT', 'created_at' => $now, 'updated_at' => $now]);
        $sem = DB::table('semesters')->insertGetId(['program_id' => 1, 'number' => 1, 'name' => 'S1', 'weeks_count' => 15, 'academic_year_id' => 1, 'created_at' => $now, 'updated_at' => $now]);

        $prof1 = User::create(['name' => 'Prof limite', 'email' => 'limite@example.com',
            'password' => bcrypt('password'), 'role' => 'prof', 'max_weekly_hours' => $profMaxHours]);
        $prof2 = User::create(['name' => 'Prof libre', 'email' => 'libre@example.com',
            'password' => bcrypt('password'), 'role' => 'prof', 'max_weekly_hours' => 99]);

        foreach (['Module 1', 'Module 2', 'Module 3'] as $i => $m) {
            $mid = DB::table('modules')->insertGetId(['program_id' => 1, 'semester_id' => $sem,
                'name' => $m, 'code' => 'M' . ($i + 1), 'type' => 'cours', 'weekly_hours' => 3, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('professor_module')->insert(['professor_id' => $prof1->id, 'module_id' => $mid, 'created_at' => $now, 'updated_at' => $now]);
        }
        $mid = DB::table('modules')->insertGetId(['program_id' => 1, 'semester_id' => $sem,
            'name' => 'Module 4', 'code' => 'M4', 'type' => 'cours', 'weekly_hours' => 3, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('professor_module')->insert(['professor_id' => $prof2->id, 'module_id' => $mid, 'created_at' => $now, 'updated_at' => $now]);

        foreach ([['Monday', 1], ['Tuesday', 2], ['Wednesday', 3], ['Thursday', 4], ['Friday', 5], ['Saturday', 6]] as [$n, $p]) {
            DB::table('days')->insert(['name' => $n, 'position' => $p, 'created_at' => $now, 'updated_at' => $now]);
        }
        // Créneaux de 2h : 09-11, 11-13, 14-16, 16-18 → jusqu'à 8h/jour.
        foreach ([['09:00-11:00', '09:00', '11:00', 1], ['11:00-13:00', '11:00', '13:00', 2],
                  ['14:00-16:00', '14:00', '16:00', 3], ['16:00-18:00', '16:00', '18:00', 4]] as [$n, $s, $e, $p]) {
            Timeslot::create(['name' => $n, 'starts_at' => $s, 'ends_at' => $e, 'position' => $p]);
        }
        StudentGroup::create(['semester_id' => $sem, 'name' => 'Groupe A', 'capacity' => 30]);
        Classroom::create(['name' => 'Amphi 1', 'capacity' => 100, 'type' => 'amphi']);

        return compact('sem', 'prof1', 'prof2');
    }

    /** Si le prof a un plafond de 4h, il ne peut recevoir que 2 créneaux de 2h (4h). */
    public function test_prof_does_not_exceed_max_weekly_hours(): void
    {
        $ctx = $this->seedScenario(4);

        $report = app(AutoGenerateTimetable::class)->generate($ctx['sem']);
        $this->assertEmpty($report['skipped_report'] ?? [], implode(', ', $report['skipped_report'] ?? []));

        $minutes = $this->profMinutes($ctx['prof1']->id, $ctx['sem']);
        $this->assertLessThanOrEqual(240, $minutes, 'Le prof ne doit pas dépasser 4h = 240 min');
        $this->assertEquals(240, $minutes, 'Le prof doit atteindre exactement son plafond');
        // 3 modules × 3h par prof1 (plafond 4h → 2 sessions, 4h) + Module 4 par prof2 (1 session, 2h).
        $this->assertEquals(3, $report['sessions_generated']);
        $this->assertEquals(2, DB::table('timetable_sessions')->where('professor_id', $ctx['prof1']->id)->count());
        $this->assertEquals(1, DB::table('timetable_sessions')->where('professor_id', $ctx['prof2']->id)->count());
        // 2 modules de prof1 jamais placés (plafond atteint) = 2 × 180 min ignorées.
        // Le Module 4 de prof2 a sa 1ʳᵉ session placée ; les minutes restantes dépendent
        // de la granularité du rapport du générateur, mais le total ignoré est au moins 360 min.
        $this->assertGreaterThanOrEqual(360, $report['sessions_skipped']);
        // Invariant global : minutes placées + minutes ignorées = minutes demandées (4 modules × 180 min).
        $placedMinutes = $this->profMinutes($ctx['prof1']->id, $ctx['sem']) + $this->profMinutes($ctx['prof2']->id, $ctx['sem']);
        $this->assertEquals(720, $placedMinutes + $report['sessions_skipped']);
    }

    /** Avec un second prof disponible (max élevé), la charge bascule vers lui. */
    public function test_load_shifts_to_prof_with_higher_budget(): void
    {
        $ctx = $this->seedScenario(4);

        $report = app(AutoGenerateTimetable::class)->generate($ctx['sem']);

        // Module 4 (prof2, plafond 99h) doit être entièrement placé : 3h → 1 créneau de 2h (reste 1h < créneau).
        $p2 = DB::table('timetable_sessions')->where('professor_id', $ctx['prof2']->id)->count();
        $this->assertGreaterThanOrEqual(1, $p2, 'Le prof sans plafond doit recevoir du travail');

        // Le plafond de prof1 reste respecté même quand prof2 absorbe le reste.
        $this->assertLessThanOrEqual(240, $this->profMinutes($ctx['prof1']->id, $ctx['sem']));
    }

    /** Un prof sans plafond (max_weekly_hours NULL) n'est jamais bloqué par le budget. */
    public function test_prof_without_limit_is_never_blocked_by_budget(): void
    {
        $now = now();
        DB::table('academic_years')->insert(['name' => '2026-2027', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('departments')->insert(['name' => 'Département test', 'code' => 'DT', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('programs')->insert(['department_id' => 1, 'name' => 'Filière test', 'code' => 'FT', 'created_at' => $now, 'updated_at' => $now]);
        $sem = DB::table('semesters')->insertGetId(['program_id' => 1, 'number' => 1, 'name' => 'S1', 'weeks_count' => 15, 'academic_year_id' => 1, 'created_at' => $now, 'updated_at' => $now]);

        $prof = User::create(['name' => 'Prof sans limite', 'email' => 'illim@example.com',
            'password' => bcrypt('password'), 'role' => 'prof', 'max_weekly_hours' => null]);

        $mid = DB::table('modules')->insertGetId(['program_id' => 1, 'semester_id' => $sem,
            'name' => 'Module X', 'code' => 'MX', 'type' => 'cours', 'weekly_hours' => 4, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('professor_module')->insert(['professor_id' => $prof->id, 'module_id' => $mid, 'created_at' => $now, 'updated_at' => $now]);

        foreach ([['Monday', 1], ['Tuesday', 2], ['Wednesday', 3], ['Thursday', 4], ['Friday', 5], ['Saturday', 6]] as [$n, $p]) {
            DB::table('days')->insert(['name' => $n, 'position' => $p, 'created_at' => $now, 'updated_at' => $now]);
        }
        Timeslot::create(['name' => '09:00-11:00', 'starts_at' => '09:00', 'ends_at' => '11:00', 'position' => 1]);
        Timeslot::create(['name' => '14:00-16:00', 'starts_at' => '14:00', 'ends_at' => '16:00', 'position' => 2]);
        StudentGroup::create(['semester_id' => $sem, 'name' => 'Groupe A', 'capacity' => 30]);
        Classroom::create(['name' => 'Amphi 1', 'capacity' => 100, 'type' => 'amphi']);

        $report = app(AutoGenerateTimetable::class)->generate($sem);
        // Module 4h : 2 créneaux de 2h = 2 sessions placées, tout le quota.
        $placed = DB::table('timetable_sessions')->where('professor_id', $prof->id)->count();
        $this->assertEquals(2, $placed, 'Le prof sans plafond doit recevoir toutes ses sessions');
        $this->assertEmpty($report['skipped_report'] ?? []);
        $this->assertTrue($report['success']);
    }

    /** La re-génération (idempotence) conserve le plafond : les minutes déjà posées comptent. */
    public function test_regeneration_respects_budget_with_existing_sessions(): void
    {
        $ctx = $this->seedScenario(4);

        $report = app(AutoGenerateTimetable::class)->generate($ctx['sem']);
        $this->assertEmpty($report['skipped_report'] ?? []);

        $before = DB::table('timetable_sessions')->count();
        $report2 = app(AutoGenerateTimetable::class)->generate($ctx['sem']);

        $this->assertEquals($before, DB::table('timetable_sessions')->count(),
            'La re-génération ne doit rien ajouter au-delà des quotas et plafonds');
        $this->assertLessThanOrEqual(240, $this->profMinutes($ctx['prof1']->id, $ctx['sem']));
    }
}
