@extends('adminlte::page')

@section('title', 'Emploi du temps — ' . $semester->name)
@section('plugins.Datatables', true)
@section('page_title', 'Emploi du temps')

@section('breadcrumb')
    <ol class="breadcrumb float-sm-right">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Accueil</a></li>
        <li class="breadcrumb-item active">{{ $semester->program->name }} — {{ $semester->name }}</li>
    </ol>
@endsection

@section('content')
<div class="container-fluid">
    <!-- Messages -->
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-2"></i>
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle mr-2"></i>
            <strong>Erreur:</strong>
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <!-- Header -->
    <div class="mb-4">
        <h2 class="mb-1">{{ $semester->program->name }}</h2>
        <p class="text-muted mb-3">
            <i class="fas fa-graduation-cap mr-2"></i>
            {{ $semester->name }}
        </p>
        <a href="{{ route('dashboard') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>
            Retour au tableau de bord
        </a>
    </div>

    <!-- Sessions Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light border-0">
            <h4 class="card-title mb-0">
                <i class="fas fa-calendar-alt text-primary mr-2"></i>
                Sessions planifiées ({{ count($entries) }})
            </h4>
        </div>

        <div class="card-body p-0">
            @if (count($entries) > 0)
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mb-0" id="timetableTable">
                        <thead class="bg-light">
                            <tr>
                                <th>Jour</th>
                                <th>Créneau</th>
                                <th>Cours</th>
                                <th>Groupe</th>
                                <th>Enseignant</th>
                                <th>Salle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $entry)
                                <tr>
                                    <td class="font-weight-600">{{ $entry->day_name }}</td>
                                    <td>
                                        <span class="badge badge-info">{{ $entry->starts_at }} - {{ $entry->ends_at }}</span>
                                    </td>
                                    <td>{{ $entry->module }}</td>
                                    <td><span class="badge badge-secondary">{{ $entry->groupe }}</span></td>
                                    <td>{{ $entry->professeur }}</td>
                                    <td>{{ $entry->salle }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-inbox mb-3" style="font-size: 2rem; opacity: 0.3;"></i>
                    <p>Aucune session planifiée pour ce semestre</p>
                    
                    <form action="{{ route('timetable.generate') }}" method="POST" class="mt-3">
                        @csrf
                        <input type="hidden" name="semester_id" value="{{ $semester->id }}">
                        <input type="hidden" name="name" value="Génération {{ now()->format('d/m/Y H:i') }}">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-wand-magic-sparkles mr-2"></i>
                            Générer l'emploi du temps
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>

    <!-- Unplaced sessions: explains why some sessions could not be scheduled -->
    @if (session('unplaced') && count(session('unplaced')) > 0)
        <div class="card border-warning shadow-sm mt-4">
            <div class="card-header bg-warning bg-opacity-25 border-warning">
                <h4 class="card-title mb-0 text-dark">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Séances non placées ({{ array_sum(array_column(session('unplaced'), 'skipped')) }})
                </h4>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Matière</th>
                                <th>Placées</th>
                                <th>Non placées</th>
                                <th>Motifs</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (session('unplaced') as $module)
                                <tr>
                                    <td class="font-weight-600">{{ $module['module_name'] }}</td>
                                    <td><span class="badge badge-success">{{ $module['generated'] }}</span></td>
                                    <td><span class="badge badge-danger">{{ $module['skipped'] }}</span></td>
                                    <td>
                                        <ul class="mb-0 ps-3">
                                            @foreach ($module['errors'] as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- Export & Quality Links -->
    <div class="row mt-4">
        @if (count($entries) > 0)
            <div class="col-md-4">
                <a href="{{ route('timetable.export.pdf', $semester->id) }}" target="_blank" class="btn btn-danger btn-block">
                    <i class="fas fa-file-pdf mr-2"></i>
                    Télécharger en PDF
                </a>
            </div>
            <div class="col-md-4">
                <a href="{{ route('timetable.export.excel', $semester->id) }}" class="btn btn-success btn-block">
                    <i class="fas fa-file-excel mr-2"></i>
                    Télécharger en Excel
                </a>
            </div>
        @endif
        <div class="col-md-4">
            <a href="{{ route('timetable.quality', $semester->id) }}" class="btn btn-outline-primary btn-block">
                <i class="fas fa-chart-bar mr-2"></i>
                Voir le rapport de qualité
            </a>
        </div>
    </div>
    @if (count($entries) > 0)
        <div class="row mt-2">
            <div class="col-md-12">
                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-block">
                    <i class="fas fa-home mr-2"></i>
                    Retour au tableau de bord
                </a>
            </div>
        </div>
    @endif
    </div>
@endsection
@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof jQuery.fn.DataTable !== 'undefined') {
        jQuery('#timetableTable').DataTable({
            language: {
                sProcessing: "Traitement en cours...",
                sSearch: "Rechercher :",
                sLengthMenu: "Afficher _MENU_ éléments",
                sInfo: "Affichage de l\'élément _START_ à _END_ sur _TOTAL_ éléments",
                sInfoEmpty: "Affichage de l\'élément 0 à 0 sur 0 élément",
                sInfoFiltered: "(filtré de _MAX_ éléments au total)",
                sLoadingRecords: "Chargement en cours...",
                sZeroRecords: "Aucun élément à afficher",
                sEmptyTable: "Aucune donnée disponible dans le tableau",
                paginate: { sFirst: "Premier", sPrevious: "Précédent", sNext: "Suivant", sLast: "Dernier" },
                aria: { sortAscending: ": activer pour trier la colonne par ordre croissant", sortDescending: ": activer pour trier la colonne par ordre décroissant" }
            },
            order: [[0, 'asc']],
            lengthMenu: [10, 25, 50, 100],
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
        });
    }
});
</script>
@endpush
