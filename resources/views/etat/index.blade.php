@extends('layouts.app')

@section('title', 'État des emplois du temps')
@section('page_title', 'État des emplois du temps')

@section('breadcrumb')
<ol class="breadcrumb float-sm-right">
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Accueil</a></li>
    <li class="breadcrumb-item active">État</li>
</ol>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center">
                    <div><h4 class="mb-1"><i class="fas fa-chart-line text-primary mr-2"></i>État des emplois du temps</h4><p class="text-muted mb-0">Consultez ou téléchargez les emplois selon le périmètre souhaité.</p></div>
                    <a href="{{ route('etat.pdf.global') }}" target="_blank" class="btn btn-danger mt-2 mt-md-0"><i class="fas fa-file-pdf mr-2"></i>PDF global</a>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-4 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-layer-group mr-2"></i>Par semestre</h5></div>
                <div class="card-body">
                    <p class="small text-muted">Génère toutes les filières qui possèdent le même numéro de semestre.</p>
                    <form action="{{ route('etat.pdf.semester') }}" method="GET" target="_blank">
                        <label for="etat-semester-number">Semestre</label>
                        <select id="etat-semester-number" name="number" class="form-control" required>
                            <option value="">-- Choisir --</option>
                            @foreach($semesterNumbers as $number)<option value="{{ $number }}">Semestre {{ $number }}</option>@endforeach
                        </select>
                        <button class="btn btn-outline-danger btn-block mt-3"><i class="fas fa-file-pdf mr-2"></i>Télécharger le PDF</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-info text-white"><h5 class="mb-0"><i class="fas fa-graduation-cap mr-2"></i>Par filière et semestre</h5></div>
                <div class="card-body">
                    <p class="small text-muted">Génère uniquement l'emploi de la filière et du semestre choisis.</p>
                    <form action="{{ route('etat.pdf.program') }}" method="GET" target="_blank">
                        <label for="etat-program">Filière</label>
                        <select id="etat-program" name="program_id" class="form-control" required>
                            <option value="">-- Choisir une filière --</option>
                            @foreach($programs as $program)<option value="{{ $program->id }}">{{ $program->name }}</option>@endforeach
                        </select>
                        <label for="etat-program-semester" class="mt-2">Semestre</label>
                        <select id="etat-program-semester" name="semester_id" class="form-control" required disabled>
                            <option value="">-- Choisir d'abord une filière --</option>
                            @foreach($semesters as $semester)<option value="{{ $semester->id }}" data-program-id="{{ $semester->program_id }}">{{ $semester->name }} (S{{ $semester->number }})</option>@endforeach
                        </select>
                        <button class="btn btn-outline-danger btn-block mt-3"><i class="fas fa-file-pdf mr-2"></i>Télécharger le PDF</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-header bg-success text-white"><h5 class="mb-0"><i class="fas fa-list-alt mr-2"></i>Vue de contrôle</h5></div>
                <div class="card-body">
                    <p class="small text-muted">Ouvre la liste complète à l'écran avec recherche, tri et pagination.</p>
                    <a href="{{ route('timetable.index') }}" class="btn btn-outline-success btn-block"><i class="fas fa-eye mr-2"></i>Voir toutes les sessions</a>
                    @if($semesterNumbers->isNotEmpty())<a href="{{ route('timetable.semester-number', $semesterNumbers->first()) }}" class="btn btn-outline-secondary btn-block mt-2"><i class="fas fa-calendar-alt mr-2"></i>Voir le premier semestre</a>@endif
                </div>
            </div>
        </div>
    </div>
    <div class="card border-0 shadow-sm mt-2">
        <div class="card-body py-3"><i class="fas fa-info-circle text-primary mr-2"></i><span class="text-muted">Le PDF global regroupe tous les semestres visibles. Le PDF par semestre regroupe toutes ses filières. Le PDF par filière reste limité au semestre sélectionné.</span></div>
    </div>
</div>
@endsection
@push('js')
<script>
document.addEventListener('DOMContentLoaded', function(){
    const program=document.getElementById('etat-program');
    const semester=document.getElementById('etat-program-semester');
    if(!program||!semester)return;
    function updateSemesters(){
        const id=program.value; let visible=0;
        Array.from(semester.options).forEach(function(option,index){
            if(index===0){option.hidden=false;return;}
            const show=option.dataset.programId===id; option.hidden=!show; option.disabled=!show;
            if(show)visible++;
        });
        semester.value=''; semester.disabled=!id||visible===0;
        semester.options[0].textContent=id?(visible?'-- Choisir un semestre --':'Aucun semestre disponible'):'-- Choisir d’abord une filière --';
    }
    program.addEventListener('change',updateSemesters); updateSemesters();
});
</script>
@endpush
