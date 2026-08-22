@extends('layouts.app')

@section('title', 'Emploi du temps — Semestre '.$number)
@section('page_title', 'Emploi du temps groupé')

@section('breadcrumb')
<ol class="breadcrumb float-sm-right">
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Accueil</a></li>
    <li class="breadcrumb-item active">Semestre {{ $number }}</li>
</ol>
@endsection

@section('content')
<div class="container-fluid">
    @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>@endif
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-primary">
            <h3 class="card-title mb-0 text-white"><i class="fas fa-layer-group mr-2"></i>Toutes les filières — Semestre {{ $number }}</h3>
        </div>
        <div class="card-body">
            <p class="text-muted">{{ $semesters->count() }} filière(s) concernée(s), {{ $entries->count() }} séance(s) planifiée(s).</p>
            <div class="table-responsive">
                <table id="groupedTimetableTable" class="table table-bordered table-striped table-hover w-100">
                    <thead class="bg-light"><tr><th>Filière</th><th>Jour</th><th>Créneau</th><th>Module</th><th>Groupe</th><th>Professeur</th><th>Salle</th></tr></thead>
                    <tbody>
                    @forelse($entries as $entry)
                        <tr><td>{{ $entry->program_name }}</td><td>{{ $entry->day_name }}</td><td>{{ $entry->starts_at }} - {{ $entry->ends_at }}</td><td>{{ $entry->module }}</td><td>{{ $entry->groupe }}</td><td>{{ $entry->professeur }}</td><td>{{ $entry->salle }}</td></tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted">Aucune séance planifiée.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer"><a href="{{ route('dashboard') }}" class="btn btn-secondary"><i class="fas fa-arrow-left mr-2"></i>Retour au tableau de bord</a></div>
    </div>
</div>
@endsection
@section('plugins.Datatables', true)
@push('js')
<script>
$(function(){ $('#groupedTimetableTable').DataTable({ order:[[0,'asc'],[1,'asc']], pageLength:25, language:{ url:'https://cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json' } }); });
</script>
@endpush
