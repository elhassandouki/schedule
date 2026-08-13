@extends('adminlte::page')
@section('title', 'Professeurs')
@section('plugins.Datatables', true)
@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <h1>Gestion des professeurs</h1>
    <a href="{{ route('professors.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter un professeur</a>
</div>
@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="card">
    <div class="card-body table-responsive p-0">
        <table class="table table-bordered table-striped" id="professorTable">
            <thead>
                <tr>
                    <th>Professeur</th>
                    <th>Limite / semaine</th>
                    <th>Modules autorisés</th>
                    <th>Disponibilités</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($professors as $professor)
                @php $avails = \Illuminate\Support\Facades\DB::table('professor_availabilities')->where('professor_id', $professor->id)->orderBy('day_of_week')->get(); @endphp
                <tr>
                    <td>{{ $professor->name }}<br><small class="text-muted">{{ $professor->email }}</small></td>
                    <td>{{ $professor->max_weekly_hours ?? 'Sans limite' }}</td>
                    <td>{{ $professor->modules->pluck('code')->join(', ') ?: 'Aucun module' }}</td>
                    <td>
                        @if($avails->isEmpty())
                            <span class="badge badge-warning">Non définies (tous les jours)</span>
                        @else
                            @foreach($avails as $a)
                                <span class="badge badge-{{ $a->available?'success':'secondary' }}" title="{{ ['Lu','Ma','Me','Je','Ve','Sa','Di'][$a->day_of_week-1] ?? 'Jour '.$a->day_of_week }}">{{ ['Lu','Ma','Me','Je','Ve','Sa','Di'][$a->day_of_week-1] ?? '' }} {{ intdiv($a->start_minute,60) }}h{{ str_pad($a->start_minute%60,2,'0',STR_PAD_LEFT) }}-{{ intdiv($a->end_minute,60) }}h{{ str_pad($a->end_minute%60,2,'0',STR_PAD_LEFT) }}</span>
                            @endforeach
                        @endif
                    </td>
                    <td class="text-right">
                        <a href="{{ route('professors.availabilities', $professor) }}" class="btn btn-sm btn-outline-success" title="Disponibilités"><i class="fas fa-calendar-check"></i></a>
                        <a href="{{ route('professors.edit', $professor) }}" class="btn btn-sm btn-outline-primary" title="Modifier"><i class="fas fa-edit"></i></a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">Aucun professeur.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($professors->hasPages())<div class="card-footer">{{ $professors->links() }}</div>@endif
</div>
@endsection
@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof jQuery.fn.DataTable !== 'undefined') {
        jQuery('#professorTable').DataTable({
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
