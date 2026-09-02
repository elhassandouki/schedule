@php($importType = $importType ?? null)
@if($importType)
<div class="card card-outline card-secondary mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-file-excel text-success mr-2"></i>Import Excel</h3>
        <div class="card-tools"><a href="{{ $importType === 'professors' ? route('professors.import.template') : route('modules.import.template') }}" class="btn btn-sm btn-outline-success"><i class="fas fa-download mr-1"></i>Télécharger le modèle</a></div>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">Utilisez le modèle fourni. Les lignes sont validées avant enregistrement ; en cas d'erreur, l'import est annulé entièrement.</p>
        <form action="{{ $importType === 'professors' ? route('professors.import') : route('modules.import') }}" method="POST" enctype="multipart/form-data" class="form-row align-items-end">
            @csrf
            <div class="col-md-8">
                <label for="excel-import-file">Fichier Excel ou CSV</label>
                <div class="custom-file"><input id="excel-import-file" type="file" name="file" class="custom-file-input" accept=".xlsx,.xls,.csv,.txt" required><label class="custom-file-label" for="excel-import-file">Choisir un fichier…</label></div>
            </div>
            <div class="col-md-4 mt-2 mt-md-0"><button type="submit" class="btn btn-success btn-block"><i class="fas fa-upload mr-1"></i>Importer {{ $importType === 'professors' ? 'les professeurs' : 'les modules' }}</button></div>
        </form>
        @if($importType === 'professors')
            <p class="small text-muted mt-2 mb-0"><strong>Colonnes :</strong> name, email, password, max_weekly_hours, max_daily_minutes, module_codes. Séparez plusieurs codes par <code>;</code>.</p>
        @else
            <p class="small text-muted mt-2 mb-0"><strong>Colonnes :</strong> name, code, program_code, semester_number, academic_year_name, type, weekly_hours, professor_emails. Séparez plusieurs emails par <code>;</code>.</p>
        @endif
    </div>
</div>
@endif

@if(session('import_errors'))
<div class="alert alert-danger">
    <h5><i class="fas fa-exclamation-triangle mr-2"></i>Import annulé : erreurs détectées</h5>
    <ul class="mb-0 pl-3">
        @foreach(session('import_errors') as $error)
            <li><strong>Ligne {{ $error['row'] }} :</strong> {{ implode(' ', $error['messages']) }}</li>
        @endforeach
    </ul>
</div>
@endif
