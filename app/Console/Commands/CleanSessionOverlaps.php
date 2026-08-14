<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Supprime les sessions en double qui chevauchent sur la même salle, le même prof
 * ou le même groupe (même jour, mêmes horaires). À exécuter après pull + migrate
 * pour nettoyer les données générées avant l'introduction des triggers anti-
 * chevauchement : php artisan app:clean-session-overlaps
 */
#[Signature('app:clean-session-overlaps')]
#[Description('Supprime les sessions qui chevauchent (salle / prof / groupe occupés en double)')]
class CleanSessionOverlaps extends Command
{
    protected $signature = 'app:clean-session-overlaps';
    public function handle(): int
    {
        $removed = 0;

        // Supprimer les doublons exacts (même jour + même créneau + même ressource),
        // en gardant la session la plus ancienne.
        $kept = DB::table('timetable_sessions')->selectRaw('MIN(id) as id')
            ->groupBy(['classroom_id', 'professor_id', 'student_group_id', 'day_id', 'timeslot_id', 'semester_id'])
            ->pluck('id');
        $removed += DB::table('timetable_sessions')->whereNotIn('id', $kept)->delete();

        // Supprimer les sessions dont les horaires chevauchent celles d'une session
        // plus ancienne sur la même salle, le même prof ou le même groupe (même jour).
        do {
            $ids = DB::table('timetable_sessions as s1')
                ->join('timetable_sessions as s2', fn ($j) => $j->on('s1.day_id', '=', 's2.day_id'))
                ->join('timeslots as t1', 't1.id', '=', 's1.timeslot_id')
                ->join('timeslots as t2', 't2.id', '=', 's2.timeslot_id')
                ->whereColumn('s1.id', '>', 's2.id')
                ->whereColumn('t1.starts_at', '<', 't2.ends_at')
                ->whereColumn('t1.ends_at', '>', 't2.starts_at')
                ->where(fn ($q) => $q
                    ->whereColumn('s1.classroom_id', '=', 's2.classroom_id')
                    ->orWhereColumn('s1.professor_id', '=', 's2.professor_id')
                    ->orWhereColumn('s1.student_group_id', '=', 's2.student_group_id'))
                ->pluck('s1.id');

            if ($ids->isEmpty()) break;
            DB::table('timetable_sessions')->whereIn('id', $ids)->delete();
            $removed += $ids->count();
        } while (true);

        $this->info("{$removed} session(s) en conflit supprimée(s).");
        return Command::SUCCESS;
    }
}
