<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Generates an employment timetable from the selected semester's modules. */
class AutoGenerateTimetable
{
    /** @var array<string,int> Charge de sessions par jour/créneau (day_id.timeslot_id => total). */
    private array $daySlotLoad = [];

    /** @var array<int,int> Nombre de sessions par salle (classroom_id => total). */
    private array $roomSessionCount = [];

    /** @var array<int,array<int,int>> Minutes par prof et par jour (prof_id => [day_id => total]). */
    private array $professorDailyMinutes = [];

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

        // Nombre de sessions placées PAR JOUR/CRÉNEAU (chargement) pour répartir
        // la semaine du professeur et du groupe de façon équilibrée.
        $this->daySlotLoad = DB::table('timetable_sessions')
            ->where('semester_id', $semesterId)
            ->groupBy('day_id', 'timeslot_id')->selectRaw('day_id, timeslot_id, COUNT(*) as total')
            ->get()->mapWithKeys(fn ($row) => ["{$row->day_id}.{$row->timeslot_id}" => (int) $row->total])->all();

        // Nombre de sessions placées par salle DANS LA GÉNÉRATION COURANTE :
        // il inclut les sessions préexistantes (re-génération) et celles créées
        // à chaque placement, pour que la répartition reste équilibrée.
        $this->roomSessionCount = DB::table('timetable_sessions')
            ->where('semester_id', $semesterId)
            ->groupBy('classroom_id')->selectRaw('classroom_id, COUNT(*) as total')
            ->pluck('total', 'classroom_id')->map(fn ($v) => (int) $v)->all();

        // Budget horaire PAR PROFESSEUR (max_weekly_hours) : les minutes déjà
        // consommées par les sessions existantes du semestre sont chargées une
        // seule fois, puis mises à jour à chaque placement. Un prof ne reçoit
        // jamais de créneau qui ferait dépasser son maximum hebdomadaire
        // (max_weekly_hours ; s'il n'est pas défini, pas de plafond).
        $professorMinutes = DB::table('timetable_sessions as ts')
            ->join('timeslots as t', 't.id', '=', 'ts.timeslot_id')
            ->where('ts.semester_id', $semesterId)
            ->groupBy('ts.professor_id')
            ->selectRaw(DB::getDriverName() === 'sqlite'
                ? 'ts.professor_id, ROUND(SUM((julianday(t.ends_at) - julianday(t.starts_at)) * 1440)) as minutes'
                : 'ts.professor_id, SUM(TIME_TO_SEC(TIMEDIFF(t.ends_at, t.starts_at))) / 60 as minutes')
            ->pluck('minutes', 'professor_id')->map(fn ($v) => (int) $v)->all();

        // Budget quotidien PAR PROFESSEUR (max_daily_minutes)
        $this->professorDailyMinutes = [];
        $dailyRows = DB::table('timetable_sessions as ts')
            ->join('timeslots as t', 't.id', '=', 'ts.timeslot_id')
            ->where('ts.semester_id', $semesterId)
            ->groupBy('ts.professor_id', 'ts.day_id')
            ->selectRaw(DB::getDriverName() === 'sqlite'
                ? 'ts.professor_id, ts.day_id, ROUND(SUM((julianday(t.ends_at) - julianday(t.starts_at)) * 1440)) as minutes'
                : 'ts.professor_id, ts.day_id, SUM(TIME_TO_SEC(TIMEDIFF(t.ends_at, t.starts_at))) / 60 as minutes')
            ->get();
        foreach ($dailyRows as $row) {
            $this->professorDailyMinutes[$row->professor_id][$row->day_id] = (int) $row->minutes;
        }

        // Salle attribuée par (module + groupe) : toutes les séances du même
        // module (même groupe) se déroulent dans LA MÊME salle, afin que les
        // étudiants et le professeur retrouvent leur salle à chaque créneau.
        // Si le module a déjà des sessions (re-génération), on réutilise la
        // salle existante.
        $moduleGroupRoom = DB::table('timetable_sessions')
            ->where('semester_id', $semesterId)
            ->groupBy('module_id', 'student_group_id')
            ->selectRaw('module_id, student_group_id, MAX(classroom_id) as classroom_id')
            ->get()->mapWithKeys(fn ($row) => ["{$row->module_id}.{$row->student_group_id}" => (int) $row->classroom_id])->all();

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
                // Ordre d'exploration : on essaie d'abord de répartir les séances du
                // module sur des jours différents, et dans chaque jour sur des créneaux
                // non consécutifs, pour éviter les journées trop chargées d'un même
                // cours. On trie dynamiquement (jours/créneaux) selon la charge déjà
                // posée dans la génération : les combinaisons les moins chargées
                // (donc les jours/créneaux avec le moins de séances) sont essayées en
                // premier.
                $searchOrder = [];
                foreach ($days as $day) foreach ($slots as $slot) {
                    $searchOrder[] = [$day, $slot];
                }
                usort($searchOrder, function ($a, $b) use ($slotsDuration) {
                    $loadA = $this->daySlotLoad["{$a[0]->id}.{$a[1]->id}"] ?? 0;
                    $loadB = $this->daySlotLoad["{$b[0]->id}.{$b[1]->id}"] ?? 0;
                    if ($loadA !== $loadB) return $loadA <=> $loadB;
                    return $slotsDuration[$a[1]->id] <=> $slotsDuration[$b[1]->id];
                });

                // Suivi des jours où ce module a déjà une séance placée DANS LA
                // GÉNÉRATION COURANTE (sessions de sessions déjà posées en base +
                // celles de la boucle courante) pour éviter de concentrer les séances
                // d'un même module le même jour (pas de 2 séances consécutives).
                $modulePlacedDays = DB::table('timetable_sessions')
                    ->where('module_id', $module->id)->where('student_group_id', $group->id)
                    ->where('semester_id', $semesterId)->pluck('day_id')->flip()->all();

                foreach ($searchOrder as [$day, $slot]) {
                    if ($slot->is_lunch_break) continue;
                    $slotStart = $this->minutes($slot->starts_at);
                    $slotEnd = $this->minutes($slot->ends_at);
                    $slotMinutes = $slotEnd - $slotStart;
                    if ($slotMinutes <= 0 || $remainingMinutes < $slotMinutes) continue;
                    // Équilibrage naturel : l'ordre d'exploration (searchOrder) trie
                    // déjà les créneaux par charge croissante. On ne force pas de
                    // répartition sur plusieurs jours (qui peut bloquer si le prof
                    // n'est dispo que certains jours) : on laisse l'algorithme placer
                    // là où c'est possible, de façon équilibrée.
                    // Contrôle de conflit temporel (chevauchement horaire réel, pas seulement
                    // timeslot_id identique) : deux créneaux aux horaires qui se chevauchent
                    // ne peuvent pas partager la même salle, le même prof ou le même groupe.
                    // Salle stable par module : le module (pour ce groupe) garde LA MÊME
                    // salle pour toutes ses séances de la semaine. On essaie d'abord sa
                    // salle attribuée ; si elle est occupée à ce créneau, on en choisit une
                    // nouvelle parmi les salles libres (la moins utilisée, juste taille)
                    // et elle devient la salle du module pour la suite de la génération.
                    $candidate = null;
                    $assignedKey = "{$module->id}.{$group->id}";
                    if (isset($moduleGroupRoom[$assignedKey])) {
                        $assigned = $rooms->first(fn ($r) => $r->id === $moduleGroupRoom[$assignedKey]);
                        if ($assigned && $assigned->capacity >= $group->capacity
                            && $this->roomIsCompatible($module->type ?? 'cours', $assigned->type ?? 'cours')
                            && !$this->overlaps('classroom_id', $assigned->id, $day->id, $slotStart, $slotEnd, $semesterId, true)) {
                            $candidate = $assigned;
                        }
                    }
                    if (!$candidate) {
                        $freeRooms = $rooms->filter(fn ($r) => $r->capacity >= $group->capacity
                            && $this->roomIsCompatible($module->type ?? 'cours', $r->type ?? 'cours')
                            && !$this->overlaps('classroom_id', $r->id, $day->id, $slotStart, $slotEnd, $semesterId, true));
                        if ($freeRooms->isEmpty()) continue;
                        $candidate = $freeRooms->sortBy(
                            fn ($r) => (($this->roomSessionCount[$r->id] ?? 0) * 1000000) + $r->capacity
                        )->first();
                        // NE PAS mettre à jour moduleGroupRoom ici : on ne valide
                        // pas encore le prof/groupe. Si le placement échoue (prof
                        // indisponible, groupe bloqué), la salle stable ne doit
                        // pas changer. La mise à jour se fait après l'insert.
                    }
                    $room = $candidate;
                    $professor = $professors->first(fn ($p) => !$this->overlaps('professor_id', $p->id, $day->id, $slotStart, $slotEnd, $semesterId)
                        && $this->professorIsAvailable($p->id, $day->position, $slot)
                        && $this->professorBudgetOk($p->id, $slotMinutes, $professorMinutes)
                        && $this->professorDailyBudgetOk($p->id, $day->id, $slotMinutes));
                    if (!$professor || $this->overlaps('student_group_id', $group->id, $day->id, $slotStart, $slotEnd, $semesterId)
                        || !$this->groupCanStudy($group->id, $day->position, $day->id, $slot, $semesterId)) continue;

                    DB::table('timetable_sessions')->insert([
                        'module_id' => $module->id, 'professor_id' => $professor->id,
                        'semester_id' => $semesterId, 'student_group_id' => $group->id,
                        'classroom_id' => $room->id, 'day_id' => $day->id, 'timeslot_id' => $slot->id,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $this->roomSessionCount[$room->id] = ($this->roomSessionCount[$room->id] ?? 0) + 1;
                    $this->daySlotLoad["{$day->id}.{$slot->id}"] = ($this->daySlotLoad["{$day->id}.{$slot->id}"] ?? 0) + 1;
                    $professorMinutes[$professor->id] = ($professorMinutes[$professor->id] ?? 0) + $slotMinutes;
                    $this->professorDailyMinutes[$professor->id][$day->id] = ($this->professorDailyMinutes[$professor->id][$day->id] ?? 0) + $slotMinutes;
                    $moduleGroupRoom[$assignedKey] = (int) $room->id;
                    $modulePlacedDays[$day->id] = true;
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

    /**
     * Retourne true si une session existante utilise la même ressource (salle, prof ou
     * groupe) sur le même jour avec des horaires qui CHEVAUCHENT le créneau candidat.
     * La comparaison est temporelle (starts_at < fin AND ends_at > début), donc des
     * créneaux aux horaires qui se chevauchent mais avec des timeslot_id différents
     * déclenchent aussi le conflit : aucune salle n'est jamais double-bookée.
     *
     * Pour les SALLES, le contrôle est GLOBAL ($globalScope = true) : une salle
     * réservée par une autre filière (semestre/programme différent) au même moment
     * est considérée comme occupée. On ne double-réserve jamais une salle entre deux
     * filières. Les profs et les groupes restent contrôlés par semestre.
     */
    private function overlaps(string $column, int $id, int $dayId, int $startMinute, int $endMinute, int $semesterId, bool $globalScope = false): bool
    {
        $start = $this->formatTime($startMinute);
        $end = $this->formatTime($endMinute);
        $query = DB::table('timetable_sessions as s')
            ->join('timeslots as t', 't.id', '=', 's.timeslot_id')
            ->where($column, $id)->where('s.day_id', $dayId);
        if (!$globalScope) {
            $query->where('s.semester_id', $semesterId);
        }
        return $query->where('t.starts_at', '<', $end)->where('t.ends_at', '>', $start)->exists();
    }

    private function formatTime(int $minute): string
    {
        return sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }

    /**
     * Vérifie que le créneau ($slotMinutes) ne ferait pas dépasser au
     * professeur son budget hebdomadaire max_weekly_hours.
     *
     * @param array<int,int> $professorMinutes  suivi des minutes déjà posées
     */
    private function professorBudgetOk(int $professorId, int $slotMinutes, array &$professorMinutes): bool
    {
        $maxHours = DB::table('users')->where('id', $professorId)->value('max_weekly_hours');
        if ($maxHours === null || $maxHours <= 0) {
            return true; // pas de plafond défini pour ce professeur
        }
        $maxMinutes = (int) round((float) $maxHours * 60);
        return (int) ($professorMinutes[$professorId] ?? 0) + $slotMinutes <= $maxMinutes;
    }

    private function professorDailyBudgetOk(int $professorId, int $dayId, int $slotMinutes): bool
    {
        $maxDaily = DB::table('users')->where('id', $professorId)->value('max_daily_minutes');
        if ($maxDaily === null || $maxDaily <= 0) {
            return true;
        }
        $scheduledMinutes = $this->professorDailyMinutes[$professorId][$dayId] ?? 0;
        return ($scheduledMinutes + $slotMinutes) <= $maxDaily;
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

    /**
     * Vérifie si le type de salle est compatible avec le type de module.
     * Règles :
     * - Cours -> amphi, cours, mixte
     * - TD -> cours, mixte
     * - TP -> labo, salle_info, mixte
     */
    private function roomIsCompatible(string $moduleType, string $roomType): bool
    {
        $moduleType = strtolower($moduleType);
        $roomType = strtolower($roomType);

        if ($moduleType === 'cours') {
            return in_array($roomType, ['cours', 'amphi', 'mixte']);
        }
        if ($moduleType === 'td') {
            return in_array($roomType, ['cours', 'mixte']);
        }
        if ($moduleType === 'tp') {
            return in_array($roomType, ['labo', 'salle_info', 'mixte']);
        }
        return $roomType === 'cours' || $roomType === 'mixte';
    }
}
