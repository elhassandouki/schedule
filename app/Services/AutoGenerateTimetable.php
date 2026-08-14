<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Generates an employment timetable from the selected semester's modules. */
class AutoGenerateTimetable
{
    public function generate(int $semesterId): array
    {
        $semester = DB::table('semesters')->find($semesterId);
        if (!$semester) return ['success' => false, 'error' => "Semester #{$semesterId} not found"];

        $modules = DB::table('modules')->where('semester_id', $semesterId)
            ->where('program_id', $semester->program_id)->get();
        $groups = DB::table('student_groups')->where('semester_id', $semesterId)->get();
        $days = DB::table('days')->orderBy('position')->get();
        $slots = DB::table('timeslots')->orderBy('position')->get();
        $rooms = DB::table('classrooms')->orderBy('capacity')->get();

        if ($modules->isEmpty()) return ['success' => false, 'error' => 'No modules found for this semester'];
        if ($groups->isEmpty()) return ['success' => false, 'error' => 'No student groups found for this semester'];
        if ($days->isEmpty() || $slots->isEmpty() || $rooms->isEmpty()) return ['success' => false, 'error' => 'Missing days, timeslots, or classrooms'];

        $generated = $skipped = [];
        $totalGenerated = $totalSkipped = 0;

        $slotsDuration = [];
        foreach ($slots as $slot) {
            $slotsDuration[$slot->id] = ($this->minutes($slot->ends_at) - $this->minutes($slot->starts_at)) / 60;
        }

        foreach ($modules as $module) {
            $generated[$module->id] = 0;
            $skipped[$module->id] = [];
            $professors = DB::table('professor_module')->where('module_id', $module->id)
                ->join('users', 'users.id', '=', 'professor_module.professor_id')
                ->select('users.id')->get();

            // Volume horaire : weekly_hours = heures PAR SEMAINE.
            // L'emploi du temps est un planning récurrent hebdomadaire. Le générateur
            // respecte STRICTEMENT weekly_hours : le budget hebdomadaire est calculé en
            // minutes (weekly_hours × 60) et chaque créneau placé consomme sa durée
            // RÉELLE (fin − début du timeslot). Un créneau n'est placé que si sa durée
            // complète tient dans le budget restant : le total hebdomadaire d'un module
            // ne dépasse donc jamais weekly_hours. Ex : module 3h/semaine avec des
            // créneaux de 1h30 → 2 sessions (3h = 180 min, 2 × 90 min). weeks_count
            // (semestres) n'influence pas le nombre de sessions générées.
            $weeklyHours = (float) ($module->weekly_hours ?? 0);
            $budgetMinutes = (int) round($weeklyHours * 60);
            if ($budgetMinutes <= 0) continue;

            if ($professors->isEmpty()) {
                $skipped[$module->id][] = 'No professor is assigned to this module';
                $totalSkipped += $groups->count();
                continue;
            }

            foreach ($groups as $group) {
                // Tenir compte des sessions déjà générées pour ce module + ce groupe
                // + ce semestre : si la génération est relancée sans suppression, le
                // générateur ne doit pas ajouter de sessions au-delà du quota.
                // Budget hebdomadaire restant pour ce module + ce groupe. On soustrait
                // les minutes déjà consommées par les sessions existantes de ce groupe
                // (idempotence : une re-génération ne dépasse jamais weekly_hours).
                $minutesExpr = DB::getDriverName() === 'sqlite'
                    ? 'ROUND((julianday(t.ends_at) - julianday(t.starts_at)) * 1440)'
                    : 'TIME_TO_SEC(TIMEDIFF(t.ends_at, t.starts_at)) / 60';
                $consumedMinutes = (int) (DB::table('timetable_sessions as ts')
                    ->join('timeslots as t', 't.id', '=', 'ts.timeslot_id')
                    ->where('ts.module_id', $module->id)
                    ->where('ts.student_group_id', $group->id)
                    ->where('ts.semester_id', $semesterId)
                    ->selectRaw("COALESCE(SUM($minutesExpr), 0) as total")->value('total'));
                $remainingMinutes = max(0, $budgetMinutes - $consumedMinutes);
                foreach ($days as $day) foreach ($slots as $slot) {
                    $slotMinutes = $this->minutes($slot->ends_at) - $this->minutes($slot->starts_at);
                    if ($slotMinutes <= 0 || $remainingMinutes < $slotMinutes) continue 2;
                    $room = $rooms->first(fn ($r) => $r->capacity >= $group->capacity && !$this->exists('classroom_id', $r->id, $day->id, $slot->id, $semesterId));
                    if (!$room) continue;
                    $professor = $professors->first(fn ($p) => !$this->exists('professor_id', $p->id, $day->id, $slot->id, $semesterId)
                        && $this->professorIsAvailable($p->id, $day->position, $slot));
                    if (!$professor || $this->exists('student_group_id', $group->id, $day->id, $slot->id, $semesterId)
                        || !$this->groupCanStudy($group->id, $day->position, $day->id, $slot, $semesterId)) continue;

                    DB::table('timetable_sessions')->insert([
                        'module_id' => $module->id, 'professor_id' => $professor->id,
                        'semester_id' => $semesterId, 'student_group_id' => $group->id,
                        'classroom_id' => $room->id, 'day_id' => $day->id, 'timeslot_id' => $slot->id,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $generated[$module->id]++; $totalGenerated++;
                    $remainingMinutes -= $slotMinutes;
                }
                if ($remainingMinutes > 0 && $consumedMinutes < $budgetMinutes) {
                    $totalSkipped += $remainingMinutes;
                    $skipped[$module->id][] = "Could not fit the remaining {$remainingMinutes} minutes for {$group->name}";
                }
            }
        }

        return ['success' => $totalSkipped === 0, 'sessions_generated' => $totalGenerated,
            'sessions_skipped' => $totalSkipped, 'generated_per_module' => $generated,
            'skipped_per_module' => $skipped];
    }

    private function exists(string $column, int $id, int $dayId, int $slotId, int $semesterId): bool
    {
        return DB::table('timetable_sessions')->where($column, $id)->where('day_id', $dayId)
            ->where('timeslot_id', $slotId)->where('semester_id', $semesterId)->exists();
    }

    private function professorIsAvailable(int $professorId, int $dayOfWeek, object $slot): bool
    {
        $availability = DB::table('professor_availabilities')->where('professor_id', $professorId)
            ->where('day_of_week', $dayOfWeek)->get();
        $start = $this->minutes($slot->starts_at); $end = $this->minutes($slot->ends_at);
        if ($availability->isNotEmpty()) {
            return $availability->contains(fn ($row) => $row->available && $row->start_minute <= $start && $row->end_minute >= $end);
        }
        // Whitelist : si le professeur a défini des disponibilités pour d'autres jours,
        // les jours sans déclaration sont considérés non disponibles.
        return !DB::table('professor_availabilities')->where('professor_id', $professorId)->exists();
    }

    private function groupCanStudy(int $groupId, int $dayOfWeek, int $dayId, object $slot, int $semesterId): bool
    {
        $condition = DB::table('group_study_conditions')->where('student_group_id', $groupId)
            ->where('day_of_week', $dayOfWeek)->first();
        if (!$condition) {
            // Whitelist : si le groupe a défini des conditions pour d'autres jours,
            // les jours sans déclaration sont considérés non disponibles.
            return !DB::table('group_study_conditions')->where('student_group_id', $groupId)->exists();
        }
        $start = $this->minutes($slot->starts_at); $end = $this->minutes($slot->ends_at);
        if ($condition->start_minute > $start || $condition->end_minute < $end) return false;
        $minutesExpr = DB::getDriverName() === 'sqlite'
            ? 'COALESCE(SUM(ROUND((julianday(t.ends_at) - julianday(t.starts_at)) * 1440)), 0)'
            : 'COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(t.ends_at, t.starts_at)) / 60), 0)';
        $scheduledMinutes = DB::table('timetable_sessions as ts')->join('timeslots as t', 't.id', '=', 'ts.timeslot_id')
            ->where('ts.student_group_id', $groupId)->where('ts.semester_id', $semesterId)->where('ts.day_id', $dayId)
            ->selectRaw("$minutesExpr as total")->value('total');
        return ((int) $scheduledMinutes + $end - $start) <= $condition->max_daily_minutes;
    }

    private function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));
        return $hours * 60 + $minutes;
    }
}
