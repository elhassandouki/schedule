<?php
namespace App\Http\Controllers;

use App\Models\Semester;
use App\Models\Teacher;
use App\Models\TimetableSession;
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
            'recentSemesters' => Semester::withCount('timetableSessions')->latest()->take(6)->get(),
            'semesters' => DB::table('semesters')->join('programs', 'programs.id', '=', 'semesters.program_id')->select('semesters.*', 'programs.name as program')->get(),
        ]);
    }

    public function generate(Request $request, AutoGenerateTimetable $generator)
    {
        $data = $request->validate(['semester_id' => 'required|exists:semesters,id', 'name' => 'required|string|max:100']);
        $report = $generator->generate((int) $data['semester_id']);

        return redirect()->route('timetable.show', $data['semester_id'])
            ->with('generation', $report['success'] ? 'Emploi généré sans conflit.' : 'Génération partielle : certaines séances n’ont pas pu être placées.')
            ->with('unplaced', []);
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

        $teacherIds = Teacher::where('user_id', $user->id)->pluck('id');
        return $teacherIds->isNotEmpty() && TimetableSession::where('semester_id', $semester->id)
            ->whereIn('teacher_id', $teacherIds)
            ->exists();
    }

    private function resolveEntriesForSemester(Semester $semester, $user, bool $isAdminOrChef)
    {
        $semesterId = $semester->id;
        $query = DB::table('timetable_sessions as ts')
            ->join('subjects as s', 's.id', '=', 'ts.subject_id')
            ->join('sections as sec', 'sec.id', '=', 'ts.section_id')
            ->join('teachers as teach', 'teach.id', '=', 'ts.teacher_id')
            ->join('classrooms as c', 'c.id', '=', 'ts.classroom_id')
            ->join('days as d', 'd.id', '=', 'ts.day_id')
            ->join('timeslots as slot', 'slot.id', '=', 'ts.timeslot_id')
            ->where('ts.semester_id', $semesterId);

        if (!$isAdminOrChef) {
            $teacherIds = Teacher::where('user_id', $user->id)->pluck('id');
            $query->whereIn('ts.teacher_id', $teacherIds);
        }

        return $query
            ->orderBy('d.position')
            ->orderBy('slot.position')
            ->select(
                'ts.id',
                'ts.day_id',
                'ts.timeslot_id',
                's.name as module',
                'sec.name as groupe',
                'teach.name as professeur',
                'c.name as salle',
                'd.name as day_name',
                'slot.name as timeslot_name',
                'slot.starts_at',
                'slot.ends_at',
            )
            ->get();
    }
}

