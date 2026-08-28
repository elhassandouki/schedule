<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use App\Services\TimetableExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class TimetableExportController extends Controller
{
    public function __construct(private TimetableExportService $service)
    {
    }

    /**
     * Download the timetable of a semester as PDF.
     */
    public function pdf(Request $request, Semester $semester)
    {
        $user = $request->user();
        $this->authorizeAccess($user, $semester);

        $data = $this->service->collect($semester, $user);
        $entries = $data['entries'];

        if ($entries->isEmpty()) {
            return redirect()->back()->withErrors([
                'export' => 'Aucune session planifiée pour ce semestre, impossible d\'exporter.',
            ]);
        }

        $html = view('exports.timetable_pdf', [
            'semester' => $data['semester'],
            'program' => $data['program'],
            'academicYear' => $data['academicYear'],
            'allDays' => $data['allDays'],
            'allSlots' => $data['allSlots'],
            'entries' => $entries,
            'generatedAt' => now()->format('d/m/Y H:i'),
            'counts' => $data['counts'],
            'exportService' => $this->service,
        ])->render();

        $fileName = 'emploi_du_temps_' . $data['slug'] . '.pdf';

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->stream($fileName);
    }

    /**
     * Download the timetable of a semester as Excel (XLSX).
     */
    public function excel(Request $request, Semester $semester)
    {
        $user = $request->user();
        $this->authorizeAccess($user, $semester);

        $data = $this->service->collect($semester, $user);

        if ($data['entries']->isEmpty()) {
            return redirect()->back()->withErrors([
                'export' => 'Aucune session planifiée pour ce semestre, impossible d\'exporter.',
            ]);
        }

        $fileName = 'emploi_du_temps_' . $data['slug'] . '.xlsx';

        return Excel::download(
            new \App\Exports\TimetableExport($data),
            $fileName
        );
    }

    public function etat(Request $request)
    {
        $user = $request->user();
        $semesters = $this->visibleSemesters($user)->get();
        $programs = $semesters->map(fn ($semester) => (object) ['id' => $semester->program_id, 'name' => $semester->program_name])->unique('id')->sortBy('name')->values();
        $semesterNumbers = $semesters->pluck('number')->unique()->sort()->values();
        $professors = $this->visibleProfessors($user)->get();
        $semesterIds = $semesters->pluck('id');
        $professorStats = $professors->map(function ($professor) use ($semesterIds) {
            $modules = DB::table('timetable_sessions as ts')
                ->join('modules as m', 'm.id', '=', 'ts.module_id')
                ->where('ts.professor_id', $professor->id)
                ->when($semesterIds->isNotEmpty(), fn ($query) => $query->whereIn('ts.semester_id', $semesterIds))
                ->select('m.name')->distinct()->orderBy('m.name')->pluck('name');
            $professor->planned_modules = $modules;
            return $professor;
        });
        return view('etat.index', compact('programs', 'semesters', 'semesterNumbers', 'professors', 'professorStats'));
    }

    /**
     * Emploi d'un professeur : toutes ses séances, regroupées par filière, semestre et groupe.
     */
    public function professorPdf(Request $request)
    {
        $data = $request->validate(['professor_id' => 'required|exists:users,id']);
        $user = $request->user();
        $professor = DB::table('users')->where('id', $data['professor_id'])->where('role', 'prof')->first();
        abort_unless($professor, 404);

        $semesters = $this->visibleSemesters($user)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('timetable_sessions as professor_ts')
                ->whereColumn('professor_ts.semester_id', 's.id')->where('professor_ts.professor_id', $professor->id))
            ->get();
        if ($semesters->isEmpty()) return redirect()->back()->withErrors(['export' => 'Aucune séance planifiée pour ce professeur.']);

        $dataSet = $this->service->collectMany($semesters, $user, (int) $professor->id);
        if ($dataSet['entries']->isEmpty()) return redirect()->back()->withErrors(['export' => 'Aucune séance planifiée pour ce professeur.']);
        $years = collect($semesters)->pluck('academic_year_id')->unique()->map(fn ($id) => DB::table('academic_years')->where('id', $id)->value('name'))->filter()->values();
        $html = view('exports.timetable_grouped_pdf', [
            'sections' => $dataSet['sections'], 'allDays' => $dataSet['allDays'], 'allSlots' => $dataSet['allSlots'],
            'title' => 'Emploi du professeur '.$professor->name, 'academicYearLabel' => $years->implode(' / '), 'exportService' => $this->service,
        ])->render();
        return Pdf::loadHTML($html)->setPaper('a4', 'landscape')->stream('emploi_professeur_'.$professor->id.'.pdf');
    }

    /**
     * État global : un seul PDF contenant tous les semestres et toutes les filières.
     */
    public function globalPdf(Request $request)
    {
        $user = $request->user();
        $semesters = $this->visibleSemesters($user)->get();
        return $this->streamGroupedPdf($semesters, $user, 'Emploi global de l’établissement', 'etat_global');
    }

    /** État filtré par numéro de semestre, toutes filières confondues. */
    public function semesterPdf(Request $request)
    {
        $data = $request->validate(['number' => 'required|integer|min:1']);
        $semesters = $this->visibleSemesters($request->user())->where('s.number', $data['number'])->get();
        return $this->streamGroupedPdf($semesters, $request->user(), 'Toutes les filières — Semestre '.$data['number'], 'etat_semestre_'.$data['number']);
    }

    /** État filtré par filière et semestre concret. */
    public function programPdf(Request $request)
    {
        $data = $request->validate([
            'program_id' => 'required|exists:programs,id',
            'semester_id' => 'required|exists:semesters,id',
        ]);
        $semesters = $this->visibleSemesters($request->user())
            ->where('s.id', $data['semester_id'])->where('s.program_id', $data['program_id'])->get();
        return $this->streamGroupedPdf($semesters, $request->user(), 'Emploi par filière et semestre', 'etat_filiere_'.$data['program_id'].'_semestre_'.$data['semester_id']);
    }

    private function visibleProfessors($user)
    {
        $isAdminOrChef = in_array($user->role, ['super_admin', 'sous_admin', 'chef_departement', 'chef_filiere'], true);
        $query = DB::table('users')->where('role', 'prof')->orderBy('name');
        if ($user->role === 'chef_departement' && $user->department_id) $query->where('department_id', $user->department_id);
        if ($user->role === 'chef_filiere' && $user->program_id) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('timetable_sessions as prof_ts')
                ->join('semesters as prof_sem', 'prof_sem.id', '=', 'prof_ts.semester_id')
                ->whereColumn('prof_ts.professor_id', 'users.id')->where('prof_sem.program_id', $user->program_id));
        }
        if (!$isAdminOrChef) $query->where('id', $user->id);
        return $query;
    }

    private function visibleSemesters($user)
    {
        $isAdminOrChef = in_array($user->role, ['super_admin', 'sous_admin', 'chef_departement', 'chef_filiere'], true);
        $query = Semester::query()->from('semesters as s')->with('program')->join('programs as p', 'p.id', '=', 's.program_id')->select('s.*', 'p.name as program_name')->orderBy('p.name')->orderBy('s.number');
        if ($user->role === 'chef_departement' && $user->department_id) $query->where('p.department_id', $user->department_id);
        if ($user->role === 'chef_filiere' && $user->program_id) $query->where('s.program_id', $user->program_id);
        if (!$isAdminOrChef) $query->whereExists(fn ($q) => $q->selectRaw('1')->from('timetable_sessions as access_ts')->whereColumn('access_ts.semester_id', 's.id')->where('access_ts.professor_id', $user->id));
        return $query;
    }

    private function streamGroupedPdf($semesters, $user, string $title, string $fileBase)
    {
        if ($semesters->isEmpty()) return redirect()->back()->withErrors(['export' => 'Aucun semestre correspondant à cette sélection.']);
        $data = $this->service->collectMany($semesters, $user);
        if ($data['entries']->isEmpty()) return redirect()->back()->withErrors(['export' => 'Aucune session planifiée pour cette sélection.']);
        $years = collect($semesters)->pluck('academic_year_id')->unique()->map(fn ($id) => \Illuminate\Support\Facades\DB::table('academic_years')->where('id', $id)->value('name'))->filter()->values();
        $html = view('exports.timetable_grouped_pdf', [
            'sections' => $data['sections'], 'allDays' => $data['allDays'], 'allSlots' => $data['allSlots'],
            'title' => $title, 'academicYearLabel' => $years->implode(' / '), 'exportService' => $this->service,
        ])->render();
        return Pdf::loadHTML($html)->setPaper('a4', 'landscape')->stream($fileBase.'.pdf');
    }

    private function authorizeAccess($user, Semester $semester): void
    {
        $isAdminOrChef = in_array($user->role, ['super_admin', 'sous_admin', 'chef_departement', 'chef_filiere'], true);
        if (!$isAdminOrChef) {
            if (!\App\Models\TimetableSession::where('semester_id', $semester->id)->where('professor_id', $user->id)->exists()) {
                abort(403, 'Accès non autorisé à cet emploi du temps.');
            }
        } else {
            if (in_array($user->role, ['chef_departement'], true) && $user->department_id) {
                if (!$semester->program()->exists() || $semester->program->department_id !== $user->department_id) {
                    abort(403, 'Accès non autorisé à cet emploi du temps.');
                }
            }
            if ($user->role === 'chef_filiere' && $user->program_id && $semester->program_id !== $user->program_id) {
                abort(403, 'Accès non autorisé à cet emploi du temps.');
            }
        }
    }
}
