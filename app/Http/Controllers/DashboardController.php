<?php
namespace App\Http\Controllers;

use App\Models\Semester;
use App\Models\TimetableSession;
use App\Models\ScheduleHistory;
use App\Services\AutoGenerateTimetable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dashboard', [
            'counts' => [
                'filières' => DB::table('programs')->count(),
                'semestres' => DB::table('semesters')->count(),
                'groupes' => DB::table('student_groups')->count(),
                'professeurs' => DB::table('users')->where('role', 'prof')->count(),
            ],
            'schedules' => ScheduleHistory::latest()->take(6)->get(),
            'semesters' => DB::table('semesters')->join('programs', 'programs.id', '=', 'semesters.program_id')->select('semesters.*', 'programs.name as program')->get(),
            'semesterOptions' => DB::table('semesters')->select('number')->selectRaw('COUNT(*) as programs_count')->groupBy('number')->orderBy('number')->get(),
            'programs' => DB::table('programs')->orderBy('name')->get(),
            'timetable_status' => $this->timetableStatusData(),
            'reference' => [
                'years' => DB::table('academic_years')->count(),
                'departments' => DB::table('departments')->count(),
                'programs' => DB::table('programs')->count(),
                'semesters' => DB::table('semesters')->count(),
                'classrooms' => DB::table('classrooms')->count(),
                'groupes' => DB::table('student_groups')->count(),
                'teachers' => DB::table('users')->where('role', 'prof')->count(),
                'modules' => DB::table('modules')->count(),
                'timeslots' => DB::table('timeslots')->count(),
                'days' => DB::table('days')->count(),
            ],
            'wizard' => $this->wizardData(),
            'charts' => $this->chartsData(),
        ]);
    }

    /**
     * Aggregate statistics for the dashboard charts (progress per entity,
     * sessions per day, recent generation trend, distribution per filière).
     */
    private function chartsData(): array
    {
        $days = DB::table('days')->orderBy('position')->get(['id', 'name']);
        $dayNames = $days->pluck('name')->all();

        // Sessions per day of week (latest generation)
        $sessionsPerDay = collect(array_fill(0, $days->count(), 0));
        if ($days->isNotEmpty()) {
            $counts = DB::table('timetable_sessions as ts')
                ->join('days as d', 'd.id', '=', 'ts.day_id')
                ->groupBy('d.id')
                ->pluck(DB::raw('count(*)'), 'd.id');
            foreach ($counts as $dayId => $count) {
                $index = $days->search(fn ($day) => $day->id == $dayId);
                if ($index !== false) {
                    $sessionsPerDay[$index] = (int) $count;
                }
            }
        }

        // Sessions per timeslot for the latest day to build a heatmap-like bar
        $sessionsPerSlot = [];
        $slotLabels = [];
        $slots = DB::table('timeslots')->orderBy('position')->get(['id', 'name', 'starts_at']);
        if ($slots->isNotEmpty()) {
            $slotCounts = DB::table('timetable_sessions')
                ->groupBy('timeslot_id')
                ->pluck(DB::raw('count(*)'), 'timeslot_id');
            foreach ($slots as $slot) {
                $slotLabels[] = $slot->starts_at . 'h';
                $sessionsPerSlot[] = $slotCounts->get($slot->id, 0);
            }
        }

        // Distribution of groups by semester (with program name)
        $groupsBySemester = DB::table('student_groups as sg')
            ->join('semesters as s', 's.id', '=', 'sg.semester_id')
            ->join('programs as p', 'p.id', '=', 's.program_id')
            ->select('s.id', DB::raw('concat(p.name, " — Semestre ", s.number) as label'))
            ->addSelect(DB::raw('count(*) as cnt'))
            ->groupBy('s.id')
            ->pluck('cnt', 'label');

        // Modules per filière
        $modulesByProgram = DB::table('modules as m')
            ->join('programs as p', 'p.id', '=', 'm.program_id')
            ->groupBy('p.id')
            ->pluck(DB::raw('count(*)'), 'p.name');

        // Generation trend (per day, last 10 days)
        $trend = [];
        for ($i = 9; $i >= 0; $i--) {
            $day = now()->subDays($i)->format('Y-m-d');
            $trend[$day] = ScheduleHistory::whereDate('created_at', $day)->count();
        }

        return [
            'sessionsPerDay' => ['labels' => $dayNames, 'values' => $sessionsPerDay->all()],
            'sessionsPerSlot' => ['labels' => $slotLabels, 'values' => $sessionsPerSlot],
            'groupsBySemester' => ['labels' => $groupsBySemester->keys()->all(), 'values' => $groupsBySemester->values()->all()],
            'modulesByProgram' => ['labels' => $modulesByProgram->keys()->all(), 'values' => $modulesByProgram->values()->all()],
            'generationTrend' => ['labels' => array_map(fn ($d) => substr($d, 5), array_keys($trend)), 'values' => array_values($trend)],
        ];
    }

    /**
     * État des emplois du temps par semestre : pour chaque semestre on agrège
     * le quota horaire attendu (weekly_hours des modules), le nombre de
     * séances réellement placées, le taux de couverture, les modules sans
     * professeur, la dernière génération et les salles occupées. L'admin peut
     * ainsi voir d'un coup d'œil ce qui est prêt, ce qui est partiel et ce
     * qu'il reste à faire.
     */
    private function timetableStatusData(): array
    {
        $semesters = DB::table('semesters as s')
            ->join('programs as p', 'p.id', '=', 's.program_id')
            ->select('s.id', 's.number', 's.name as semester_name', 's.program_id', 'p.name as program_name')
            ->orderBy('p.name')
            ->orderBy('s.number')
            ->get();

        $semIds = $semesters->pluck('id')->all();

        // Précharger les quotas et placements en une requête par semestre max,
        // puis les sessions placées pour tous les semestres d'un coup.
        $moduleQuotas = DB::table('modules')
            ->whereIn('semester_id', $semIds)
            ->select('id as module_id', 'name as module_name', 'semester_id', 'weekly_hours')
            ->get();

        $placed = DB::table('timetable_sessions')
            ->whereIn('semester_id', $semIds)
            ->selectRaw('semester_id, module_id, COUNT(*) as sessions')
            ->groupBy('semester_id', 'module_id')
            ->get()
            ->groupBy('semester_id');

        // Précharger les affectations prof-module une seule fois.
        $profAssigned = DB::table('professor_module')->pluck('module_id');

        $status = [];
        $semModuleQuotas = $moduleQuotas->groupBy('semester_id');
        foreach ($semesters as $sem) {
            $moduleQuotas = ($semModuleQuotas[$sem->id] ?? collect())->values();

            $placedForSem = ($placed[$sem->id] ?? collect())
                ->pluck('sessions', 'module_id');

            $expectedMinutes = $moduleQuotas->sum('weekly_hours') * 60;
            // Durée totale des créneaux en minutes : compatible MySQL et SQLite.
            $driver = DB::connection()->getDriverName();
            $diffExpr = $driver === 'sqlite'
                ? "SUM((strftime('%s', ends_at) - strftime('%s', starts_at)) / 60)"
                : 'SUM(TIMESTAMPDIFF(MINUTE, starts_at, ends_at))';
            $totalSlots = DB::table('timeslots')->selectRaw("$diffExpr as total")->value('total');
            $totalSlots = max((int) $totalSlots, 1);

            $placedMinutes = 0;
            foreach ($moduleQuotas as $mq) {
                if ($placedForSem->has($mq->module_id)) {
                    $placedMinutes += min($mq->weekly_hours * 60, (int) ($placedForSem[$mq->module_id] * $totalSlots));
                }
            }

            $coverage = $expectedMinutes > 0 ? min(100, (int) round(($placedMinutes / $expectedMinutes) * 100)) : 0;

            $missingProf = $moduleQuotas->filter(fn ($mq) =>
                !$profAssigned->contains($mq->module_id)
            )->values();

            $lastGeneration = ScheduleHistory::where('semester_id', $sem->id)->latest()->first();

            $totalRooms = DB::table('classrooms')->count();
            $usedRooms = DB::table('timetable_sessions as ts')
                ->where('ts.semester_id', $sem->id)
                ->distinct()
                ->count('ts.classroom_id');

            $state = $coverage >= 100 ? 'complete' : ($placedMinutes > 0 ? 'partial' : 'empty');

            $status[] = [
                'semester' => $sem,
                'module_count' => $moduleQuotas->count(),
                'expected_minutes' => $expectedMinutes,
                'placed_minutes' => $placedMinutes,
                'coverage' => $coverage,
                'state' => $state,
                'missing_prof_count' => $missingProf->count(),
                'missing_prof_modules' => $missingProf,
                'last_generation' => $lastGeneration,
                'total_rooms' => $totalRooms,
                'used_rooms' => $usedRooms,
            ];
        }

        $totals = [
            'semesters' => count($status),
            'complete' => count(array_filter($status, fn ($s) => $s['state'] === 'complete')),
            'partial' => count(array_filter($status, fn ($s) => $s['state'] === 'partial')),
            'empty' => count(array_filter($status, fn ($s) => $s['state'] === 'empty')),
            'missing_prof_modules' => array_sum(array_map(fn ($s) => $s['missing_prof_count'], $status)),
        ];

        return ['items' => $status, 'totals' => $totals];
    }

    /**
     * Guided setup steps following the logical dependency order of the
     * reference data (days & slots first, then years, departments,
     * programs, semesters, and finally classrooms, teachers, groups, subjects).
     */
    private function wizardData(): array
    {
        $steps = [
            ['key' => 'days', 'label' => 'Jours de la semaine', 'resource' => 'days', 'table' => 'days', 'desc' => 'Ex : Lundi, Mardi, ...', 'icon' => 'fas fa-calendar-day'],
            ['key' => 'timeslots', 'label' => 'Créneaux horaires', 'resource' => 'timeslots', 'table' => 'timeslots', 'desc' => 'Ex : 08:00-10:00', 'icon' => 'fas fa-clock'],
            ['key' => 'years', 'label' => 'Année universitaire', 'resource' => 'annees', 'table' => 'academic_years', 'desc' => 'Ex : 2026/2027', 'icon' => 'fas fa-calendar-check'],
            ['key' => 'departments', 'label' => 'Départements', 'resource' => 'departements', 'table' => 'departments', 'desc' => 'Ex : Département Informatique', 'icon' => 'fas fa-building'],
            ['key' => 'programs', 'label' => 'Filières', 'resource' => 'filieres', 'table' => 'programs', 'desc' => 'Rattachées à un département', 'icon' => 'fas fa-graduation-cap'],
            ['key' => 'semesters', 'label' => 'Semestres', 'resource' => 'semestres', 'table' => 'semesters', 'desc' => 'Rattachés à une filière et une année', 'icon' => 'fas fa-layer-group'],
            ['key' => 'classrooms', 'label' => 'Salles', 'resource' => 'salles', 'table' => 'classrooms', 'desc' => 'Avec capacité et type (cours/TD/TP)', 'icon' => 'fas fa-door-open'],
            ['key' => 'teachers', 'label' => 'Enseignants', 'resource' => 'teachers', 'table' => 'teachers', 'desc' => 'Corps enseignant', 'icon' => 'fas fa-chalkboard-teacher'],
            ['key' => 'sections', 'label' => 'Groupes', 'resource' => 'sections', 'table' => 'sections', 'desc' => 'Groupes d’étudiants par filière', 'icon' => 'fas fa-users'],
            ['key' => 'subjects', 'label' => 'Matières', 'resource' => 'subjects', 'table' => 'subjects', 'desc' => 'Rattachées à un semestre, enseignant et groupe', 'icon' => 'fas fa-book-open'],
        ];
        $steps = array_values(array_filter($steps, fn (array $step) => $step['key'] !== 'subjects'));
        $steps = array_values(array_filter($steps, fn (array $step) => !in_array($step['key'], ['sections', 'teachers'], true)));
        $steps[] = ['key' => 'modules', 'label' => 'Modules', 'resource' => 'modules', 'table' => 'modules', 'desc' => 'Rattachés à une filière et un semestre'];
        $steps[] = ['key' => 'groupes', 'label' => 'Groupes', 'resource' => 'groupes', 'table' => 'student_groups', 'desc' => 'Rattachés à un semestre avec leur capacité'];

        foreach ($steps as &$step) {
            $step['count'] = (int) DB::table($step['table'])->count();
            $step['done'] = $step['count'] > 0;
        }
        unset($step);

        $next = null;
        foreach ($steps as $step) {
            if (!$step['done']) {
                $next = $step;
                break;
            }
        }

        return [
            'steps' => $steps,
            'next' => $next,
            'ready' => collect($steps)->every(fn ($s) => $s['done']),
        ];
    }

    public function generate(Request $request, AutoGenerateTimetable $generator)
    {
        $data = $request->validate([
            'semester_number' => 'nullable|integer|min:1',
            'semester_id' => 'nullable|exists:semesters,id',
            'name' => 'required|string|max:100',
        ]);
        if (empty($data['semester_number']) && empty($data['semester_id'])) {
            return redirect()->back()->withErrors(['semester_number' => 'Sélectionnez un semestre.']);
        }
        if (!empty($data['semester_number'])) {
            return $this->generateForSemesterNumber((int) $data['semester_number'], $data['name'], $generator);
        }
        
        $semesterId = (int) $data['semester_id'];
        $semester = DB::table('semesters')->find($semesterId);
        
        // Validate required data exists
        $modules = DB::table('modules')->where('semester_id', $semesterId)->where('program_id', $semester->program_id)->count();
        $studentGroups = DB::table('student_groups')->where('semester_id', $semesterId)->count();
        $classrooms = DB::table('classrooms')->count();
        $days = DB::table('days')->count();
        $timeslots = DB::table('timeslots')->count();

        $missing = [];
        if ($modules === 0) $missing[] = 'Modules (courses for this semester)';
        if ($studentGroups === 0) $missing[] = 'Student Groups (for this semester)';
        if ($classrooms === 0) $missing[] = 'Classrooms (rooms)';
        if ($days === 0) $missing[] = 'Days (work days)';
        if ($timeslots === 0) $missing[] = 'Timeslots (time periods)';

        if (!empty($missing)) {
            return redirect()->back()->withErrors([
                'generation' => 'Cannot generate: Missing ' . implode(', ', $missing) . '. Create these in the admin panel first.'
            ]);
        }

        // Run generation algorithm
        $report = $generator->generate($semesterId);

        // Handle error from algorithm
        if (isset($report['error'])) {
            return redirect()->back()->withErrors(['generation' => $report['error']]);
        }

        // Record generation history only when the generator actually ran.
        // A hard error (missing subjects, groups, days, ...) must never be
        // recorded as a 'partial' history with zero sessions.
        if (array_key_exists('sessions_generated', $report)) {
            ScheduleHistory::create([
                'semester_id' => $semesterId,
                'name' => $data['name'],
                'status' => $report['success'] ? 'generated' : 'partial',
                'generated_sessions_count' => $report['sessions_generated'] ?? 0,
                'skipped_sessions_count' => $report['sessions_skipped'] ?? 0,
                'generated_by_user_id' => auth()->id(),
            ]);
        }

        $message = "Emploi généré : " . ($report['sessions_generated'] ?? 0) . " séance(s) placée(s)";
        if (($report['sessions_skipped'] ?? 0) > 0) {
            $message .= " (" . ($report['sessions_skipped'] ?? 0) . " non placée(s) à cause de conflits)";
        }

        $unplaced = collect($report['skipped_per_module'] ?? [])
            ->map(fn (array $errors, $moduleId) => [
                'module_name' => DB::table('modules')->where('id', $moduleId)->value('name'),
                'generated' => $report['generated_per_module'][$moduleId] ?? 0,
                'skipped' => count($errors), 'errors' => $errors,
            ])->filter(fn (array $module) => $module['skipped'] > 0)->values()->all();

        return redirect()->route('timetable.show', $semesterId)
            ->with('success', $message)
            ->with('generation_report', $report)
            ->with('unplaced', $unplaced);

    }

    private function generateForSemesterNumber(int $number, string $name, AutoGenerateTimetable $generator)
    {
        $query = DB::table('semesters as s')->where('s.number', $number);
        $activeYearIds = DB::table('academic_years')->where('is_active', true)->pluck('id');
        if ($activeYearIds->isNotEmpty()) $query->whereIn('s.academic_year_id', $activeYearIds);
        $semesters = $query->orderBy('s.program_id')->pluck('s.id');
        if ($semesters->isEmpty()) {
            return redirect()->back()->withErrors(['generation' => "Aucun semestre {$number} n'est disponible."]);
        }

        $totalGenerated = 0; $totalSkipped = 0; $allUnplaced = [];
        foreach ($semesters as $semesterId) {
            $missing = [];
            if (DB::table('modules')->where('semester_id', $semesterId)->count() === 0) $missing[] = 'Modules';
            if (DB::table('student_groups')->where('semester_id', $semesterId)->count() === 0) $missing[] = 'Groupes';
            if (DB::table('classrooms')->count() === 0) $missing[] = 'Salles';
            if (DB::table('days')->count() === 0) $missing[] = 'Jours';
            if (DB::table('timeslots')->count() === 0) $missing[] = 'Créneaux';
            if (!empty($missing)) {
                $allUnplaced[] = ['semester_id' => $semesterId, 'errors' => ['Données manquantes : ' . implode(', ', $missing)]];
                continue;
            }
            $report = $generator->generate((int) $semesterId);
            if (isset($report['error'])) {
                $allUnplaced[] = ['semester_id' => $semesterId, 'errors' => [$report['error']]];
                continue;
            }
            $totalGenerated += (int) ($report['sessions_generated'] ?? 0);
            $totalSkipped += (int) ($report['sessions_skipped'] ?? 0);
            foreach (($report['skipped_per_module'] ?? []) as $moduleId => $errors) {
                if (!empty($errors)) $allUnplaced[] = ['semester_id' => $semesterId, 'module_id' => $moduleId, 'errors' => $errors];
            }
            ScheduleHistory::create([
                'semester_id' => $semesterId,
                'name' => $name . ' — Semestre ' . $number,
                'status' => $report['success'] ? 'generated' : 'partial',
                'generated_sessions_count' => $report['sessions_generated'] ?? 0,
                'skipped_sessions_count' => $report['sessions_skipped'] ?? 0,
                'generated_by_user_id' => auth()->id(),
            ]);
        }
        $message = "Semestre {$number} généré pour {$semesters->count()} filière(s) : {$totalGenerated} séance(s) placée(s)";
        if ($totalSkipped > 0) $message .= " ({$totalSkipped} minute(s)/séance(s) non placée(s))";
        return redirect()->route('timetable.semester-number', $number)
            ->with('success', $message)->with('batch_unplaced', $allUnplaced);
    }

    public function showByNumber(Request $request, int $number)
    {
        $user = $request->user();
        $isAdminOrChef = in_array($user->role, ['super_admin', 'sous_admin', 'chef_departement', 'chef_filiere'], true);
        $semesters = DB::table('semesters as s')->join('programs as p', 'p.id', '=', 's.program_id')
            ->where('s.number', $number)->select('s.*', 'p.name as program_name')->orderBy('p.name')->get()
            ->filter(fn ($semester) => $isAdminOrChef || DB::table('timetable_sessions')->where('semester_id', $semester->id)->where('professor_id', $user->id)->exists());
        $semesterIds = $semesters->pluck('id');
        $entries = DB::table('timetable_sessions as ts')->join('modules as m', 'm.id', '=', 'ts.module_id')
            ->join('student_groups as sg', 'sg.id', '=', 'ts.student_group_id')->join('users as professor', 'professor.id', '=', 'ts.professor_id')
            ->join('classrooms as c', 'c.id', '=', 'ts.classroom_id')->join('days as d', 'd.id', '=', 'ts.day_id')->join('timeslots as slot', 'slot.id', '=', 'ts.timeslot_id')
            ->join('semesters as sem', 'sem.id', '=', 'ts.semester_id')->join('programs as p', 'p.id', '=', 'sem.program_id')
            ->whereIn('ts.semester_id', $semesterIds)->when(!$isAdminOrChef, fn ($q) => $q->where('ts.professor_id', $user->id))
            ->select('ts.id', 'ts.semester_id', 'p.name as program_name', 'm.name as module', 'sg.name as groupe', 'professor.name as professeur', 'c.name as salle', 'd.name as day_name', 'slot.name as timeslot_name', 'slot.starts_at', 'slot.ends_at')
            ->orderBy('d.position')->orderBy('slot.position')->get();
        return view('timetable.grouped', compact('number', 'semesters', 'entries'));
    }

    public function show(Request $request, Semester $semester)
    {
        $user = $request->user();
        $isAdminOrChef = in_array($user->role, ['super_admin', 'sous_admin', 'chef_departement', 'chef_filiere'], true);

        if (!$this->canAccessSemester($user, $semester, $isAdminOrChef)) {
            abort(403, 'Accès non autorisé à cet emploi du temps.');
        }

        $entries = $this->resolveEntriesForSemester($semester, $user, $isAdminOrChef);

        return view('timetable.show', ['semester' => $semester, 'entries' => $entries]);
    }

    public function destroy(ScheduleHistory $generation)
    {
        // Supprimer TOUTES les sessions du semestre lié à cette génération.
        // Le filtrage par horodatage était buggé : les sessions créées à un
        // timestamp différent de celui de l'histoire (régénérations
        // successives, sessions résiduelles) n'étaient jamais supprimées,
        // ce qui faisait apparaître l'ancien emploi du temps dans le PDF.
        $deletedSessions = TimetableSession::where('semester_id', $generation->semester_id)->delete();

        $generation->delete();

        return redirect()->back()->with('success', "Génération « {$generation->getOriginal('name')} » supprimée ({$deletedSessions} séance(s) retirée(s)).");
    }

    private function canAccessSemester($user, Semester $semester, bool $isAdminOrChef): bool
    {
        if ($isAdminOrChef) {
            if (in_array($user->role, ['super_admin', 'sous_admin'], true)) {
                return true;
            }

            if ($user->role === 'chef_departement' && $user->department_id) {
                return $semester->program()->exists() && $semester->program->department_id === $user->department_id;
            }

            if ($user->role === 'chef_filiere' && $user->program_id) {
                return $semester->program_id === $user->program_id;
            }

            return true;
        }

        return TimetableSession::where('semester_id', $semester->id)->where('professor_id', $user->id)->exists();
    }

    private function resolveEntriesForSemester(Semester $semester, $user, bool $isAdminOrChef)
    {
        $semesterId = $semester->id;
        $query = DB::table('timetable_sessions as ts')
            ->join('modules as m', 'm.id', '=', 'ts.module_id')
            ->join('student_groups as sg', 'sg.id', '=', 'ts.student_group_id')
            ->join('users as professor', 'professor.id', '=', 'ts.professor_id')
            ->join('classrooms as c', 'c.id', '=', 'ts.classroom_id')
            ->join('days as d', 'd.id', '=', 'ts.day_id')
            ->join('timeslots as slot', 'slot.id', '=', 'ts.timeslot_id')
            ->where('ts.semester_id', $semesterId);

        if (!$isAdminOrChef) {
            $query->where('ts.professor_id', $user->id);
        }

        return $query
            ->orderBy('d.position')
            ->orderBy('slot.position')
            ->select(
                'ts.id',
                'ts.day_id',
                'ts.timeslot_id',
                'm.name as module',
                'sg.name as groupe',
                'professor.name as professeur',
                'c.name as salle',
                'd.name as day_name',
                'slot.name as timeslot_name',
                'slot.starts_at',
                'slot.ends_at',
            )
            ->get();
    }
}
