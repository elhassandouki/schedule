<?php

namespace App\Http\Controllers;

use App\Exports\ModuleImportTemplateExport;
use App\Exports\ProfessorImportTemplateExport;
use App\Imports\ImportRowsException;
use App\Imports\ModulesImport;
use App\Imports\ProfessorsImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ExcelImportController extends Controller
{
    public function professorsTemplate()
    {
        return Excel::download(new ProfessorImportTemplateExport(), 'modele_import_professeurs.xlsx');
    }

    public function modulesTemplate()
    {
        return Excel::download(new ModuleImportTemplateExport(), 'modele_import_modules.xlsx');
    }

    public function professors(Request $request)
    {
        $this->validateFile($request);
        $import = new ProfessorsImport();

        try {
            DB::transaction(fn () => Excel::import($import, $request->file('file')));
        } catch (ImportRowsException $exception) {
            return redirect()->back()->with('import_errors', $exception->rowErrors)
                ->with('error', 'Import annulé : corrigez les lignes indiquées puis réessayez.');
        } catch (\Throwable $exception) {
            report($exception);
            return redirect()->back()->with('error', 'Import impossible. Vérifiez le format du fichier et ses colonnes.');
        }

        return redirect()->back()->with('success', "Import des professeurs terminé : {$import->created} créé(s), {$import->updated} mis à jour.")
            ->with('import_summary', ['created' => $import->created, 'updated' => $import->updated]);
    }

    public function modules(Request $request)
    {
        $this->validateFile($request);
        $import = new ModulesImport();

        try {
            DB::transaction(fn () => Excel::import($import, $request->file('file')));
        } catch (ImportRowsException $exception) {
            return redirect()->back()->with('import_errors', $exception->rowErrors)
                ->with('error', 'Import annulé : corrigez les lignes indiquées puis réessayez.');
        } catch (\Throwable $exception) {
            report($exception);
            return redirect()->back()->with('error', 'Import impossible. Vérifiez le format du fichier et ses colonnes.');
        }

        return redirect()->back()->with('success', "Import des modules terminé : {$import->created} créé(s), {$import->updated} mis à jour.")
            ->with('import_summary', ['created' => $import->created, 'updated' => $import->updated]);
    }

    private function validateFile(Request $request): void
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ], [
            'file.mimes' => 'Le fichier doit être au format XLSX, XLS ou CSV.',
            'file.max' => 'Le fichier ne peut pas dépasser 10 Mo.',
        ]);
    }
}
