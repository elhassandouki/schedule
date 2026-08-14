<?php

namespace Tests\Feature;

use App\Services\AutoGenerateTimetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Vérifie le respect STRICT de weekly_hours : chaque créneau placé consomme sa
 * durée réelle, et le total hebdomadaire de chaque module ne dépasse jamais
 * weekly_hours (budget minute). Cas utilisateur : module 3h/semaine avec des
 * créneaux de 1h30.
 */
class StrictWeeklyHoursTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_3h_with_90min_slots_fits_exactly_two_sessions(): void
    {
        $this->seed(\Database\Seeders\UnifiedDemoSeeder::class);

        // Remplacer les créneaux du demo par des créneaux de 1h30 (cas utilisateur :
        // weekly_hours = 3, créneaux de 1h30 → exactement 2 sessions = 3h).
        $now = now();
        DB::table('timeslots')->delete();
        DB::table('timeslots')->insert([
            ['name' => '08:00-09:30', 'starts_at' => '08:00', 'ends_at' => '09:30', 'position' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '10:00-11:30', 'starts_at' => '10:00', 'ends_at' => '11:30', 'position' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '13:00-14:30', 'starts_at' => '13:00', 'ends_at' => '14:30', 'position' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => '15:00-16:30', 'starts_at' => '15:00', 'ends_at' => '16:30', 'position' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Nettoyer les sessions et les affectations profs du demo pour isoler le test.
        DB::table('timetable_sessions')->delete();
        DB::table('professor_module')->delete();
        DB::table('professor_availabilities')->delete();

        // Un module de 3h/semaine, assigné à un seul prof disponible en semaine.
        $module = DB::table('modules')->where('weekly_hours', 6)->first();
        DB::table('modules')->where('id', $module->id)->update(['weekly_hours' => 3]);
        // Isoler le module : supprimer les autres modules et groupes du demo
        // pour que le rapport de génération ne soit pas pollué par des skips.
        DB::table('modules')->where('id', '!=', $module->id)->delete();
        $groups = DB::table('student_groups')->where('semester_id', '!=', $module->semester_id)->pluck('id');
        DB::table('student_groups')->where('id', '!=', DB::table('student_groups')->min('id'))->delete();

        $profId = (int) DB::table('users')->where('role', 'prof')->min('id');
        DB::table('professor_module')->insert([
            'professor_id' => $profId, 'module_id' => $module->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('professor_availabilities')->where('professor_id', $profId)->delete();
        foreach (range(1, 5) as $day) {
            DB::table('professor_availabilities')->insert([
                'professor_id' => $profId, 'day_of_week' => $day,
                'start_minute' => 480, 'end_minute' => 1080, 'available' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $group = DB::table('student_groups')->first();
        $semesterId = $group->semester_id;

        $report = (new AutoGenerateTimetable())->generate($semesterId);
        if (!$report['success']) {
            $this->fail('Génération échouée : ' . json_encode($report));
        }
        $this->assertSame(2, $report['sessions_generated']);
        $this->assertSame(0, $report['sessions_skipped']);

        // Vérification du total horaire réel : 2 sessions × 90 min = 180 min = 3h.
        $totalMinutes = DB::table('timetable_sessions as ts')
            ->join('timeslots as t', 't.id', '=', 'ts.timeslot_id')
            ->where('ts.module_id', $module->id)
            ->selectRaw(DB::getDriverName() === 'sqlite'
                ? 'SUM(ROUND((julianday(t.ends_at) - julianday(t.starts_at)) * 1440)) as total'
                : 'SUM(TIME_TO_SEC(TIMEDIFF(t.ends_at, t.starts_at)) / 60) as total')
            ->value('total');
        $this->assertSame(180, (int) $totalMinutes);

        // Idempotence : relance sans suppression → 0 session supplémentaire.
        $report2 = (new AutoGenerateTimetable())->generate($semesterId);
        $this->assertSame(0, $report2['sessions_generated']);
        $this->assertSame(2, DB::table('timetable_sessions')->where('module_id', $module->id)->count());
    }

    public function test_module_hours_never_exceed_weekly_hours(): void
    {
        $this->seed(\Database\Seeders\UnifiedDemoSeeder::class);
        DB::table('timetable_sessions')->delete();

        $minutesExpr = DB::getDriverName() === 'sqlite'
            ? 'ROUND((julianday(t.ends_at) - julianday(t.starts_at)) * 1440)'
            : 'TIME_TO_SEC(TIMEDIFF(t.ends_at, t.starts_at)) / 60';

        (new AutoGenerateTimetable())->generate(1);

        // Pour chaque module + groupe, le total hebdomadaire (en minutes) ne
        // doit jamais dépasser weekly_hours × 60.
        $rows = DB::table('timetable_sessions as ts')
            ->join('timeslots as t', 't.id', '=', 'ts.timeslot_id')
            ->join('modules as m', 'm.id', '=', 'ts.module_id')
            ->selectRaw("ts.module_id, ts.student_group_id, m.weekly_hours, SUM($minutesExpr) as total_minutes")
            ->groupBy('ts.module_id', 'ts.student_group_id', 'm.weekly_hours')->get();
        foreach ($rows as $row) {
            $this->assertLessThanOrEqual((int) $row->weekly_hours * 60, (int) $row->total_minutes,
                "Module {$row->module_id}/Groupe {$row->student_group_id} dépasse weekly_hours");
        }
    }
}
