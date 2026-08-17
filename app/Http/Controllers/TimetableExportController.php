<?php

namespace App\Http\Controllers;

use App\Models\Semester;
use App\Services\TimetableExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
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
