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
        ]);
    }

    /**
     * Guided setup steps following the logical dependency order of the
     * reference data (days & slots first, then years, departments,
     * programs, semesters, and finally classrooms, teachers, groups, subjects).
     */
    private function wizardData(): array
    {
        $steps = [
            ['key' => 'days', 'label' => 'Jours de la semaine', 'resource' => 'days', 'table' => 'days', 'desc' => 'Ex : Lundi, Mardi, ...'],
            ['key' => 'timeslots', 'label' => 'Créneaux horaires', 'resource' => 'timeslots', 'table' => 'timeslots', 'desc' => 'Ex : 08:00-10:00'],
            ['key' => 'years', 'label' => 'Année universitaire', 'resource' => 'annees', 'table' => 'academic_years', 'desc' => 'Ex : 2026/2027'],
            ['key' => 'departments', 'label' => 'Départements', 'resource' => 'departements', 'table' => 'departments', 'desc' => 'Ex : Département Informatique'],
            ['key' => 'programs', 'label' => 'Filières', 'resource' => 'filieres', 'table' => 'programs', 'desc' => 'Rattachées à un département'],
            ['key' => 'semesters', 'label' => 'Semestres', 'resource' => 'semestres', 'table' => 'semesters', 'desc' => 'Rattachés à une filière et une année'],
            ['key' => 'classrooms', 'label' => 'Salles', 'resource' => 'salles', 'table' => 'classrooms', 'desc' => 'Avec capacité et type (cours/TD/TP)'],
            ['key' => 'teachers', 'label' => 'Enseignants', 'resource' => 'teachers', 'table' => 'teachers', 'desc' => 'Corps enseignant'],
            ['key' => 'sections', 'label' => 'Groupes', 'resource' => 'sections', 'table' => 'sections', 'desc' => 'Groupes d’étudiants par filière'],
            ['key' => 'subjects', 'label' => 'Matières', 'resource' => 'subjects', 'table' => 'subjects', 'desc' => 'Rattachées à un semestre, enseignant et groupe'],
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
        $data = $request->validate(['semester_id' => 'required|exists:semesters,id', 'name' => 'required|string|max:100']);
        
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
