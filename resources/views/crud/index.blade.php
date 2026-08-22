@extends('adminlte::page')
@section('title', $meta['title'])
@section('plugins.Datatables', true)
@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <h1><i class="{{ $meta['icon'] ?? 'fas fa-list' }} mr-2"></i>{{ $meta['title'] }}</h1>
    <div class="d-flex gap-2">
        @if($resource === 'groupes')
        <form method="get" action="{{ route('crud.index',$resource) }}" class="d-inline-flex align-items-center mr-2">
            <select name="program_id" class="form-control form-control-sm mr-2" onchange="this.form.submit()" style="min-width:220px">
                <option value="">Toutes les filières</option>
                @php($programs = \Illuminate\Support\Facades\DB::table('programs')->orderBy('name')->get())
                @foreach($programs as $p)
                    <option value="{{ $p->id }}" @selected(($filter['program_id']??null)==$p->id)>{{ $p->name }}</option>
                @endforeach
            </select>
        </form>
        @if($filter['program_id'])
            <a href="{{ route('crud.create',$resource) }}?program_id={{ $filter['program_id'] }}" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter un groupe</a>
        @else
            <a href="{{ route('crud.create',$resource) }}" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter un groupe</a>
        @endif
        @else
        <a href="{{ route('crud.create',$resource) }}" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter</a>
        @endif
    </div>
</div>
@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

@if($resource === 'groupes' && $filter['program_id'])
    <div class="alert alert-info py-2">
        <i class="fas fa-filter"></i> Filtre actif : filière « <strong>{{ $choices['program_id'][$filter['program_id']] ?? '' }}</strong> ».
        Les semestres affichés et proposés à la création appartiennent uniquement à cette filière.
    </div>
@endif

<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title mb-0 text-white">
            <i class="{{ $meta['icon'] ?? 'fas fa-list' }} mr-2"></i>
            Liste — {{ $meta['title'] }}
        </h3>
        <div class="card-tools">
            <span class="badge badge-light"><i class="fas fa-table mr-1"></i>{{ number_format($rows->count()) }} élément(s)</span>
        </div>
    </div>
    <div class="card-body table-responsive p-0">
        <table class="table table-bordered table-striped" id="dataTable">
            <thead>
                <tr>
                    @foreach($meta['fields'] as $field=>$label)<th>{{ $label }}</th>@endforeach
                    @if($resource === "groupes")<th>Conditions</th>@endif
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                <tr>
                    @foreach($meta['fields'] as $field=>$label)
                    <td>
                        @if(($meta['types'][$field]??'')==='checkbox')
                            <span class="badge badge-{{ $row->$field?'success':'secondary' }}">{{ $row->$field?'Oui':'Non' }}</span>
                        @elseif($field === 'semester_id')
                            {{-- Semestre lié à sa filière --}}
                            @php($sem = \Illuminate\Support\Facades\DB::table('semesters')->find($row->semester_id))
                            @if($sem)
                                {{ $sem->name }}
                                @if($row->program_id)
                                    <span class="badge badge-outline-primary">{{ $choices['program_id'][$row->program_id] ?? '' }}</span>
                                @endif
                            @else — @endif
                        @elseif(isset($choices[$field]))
                            {{ $choices[$field][$row->$field] ?? '—' }}
                        @elseif($field==='password')
                            ••••••••
                        @elseif(in_array($field,['start_minute','end_minute'],true))
                            {{ intdiv($row->$field,60) }}h{{ str_pad($row->$field%60,2,'0',STR_PAD_LEFT) }}
                        @else
                            {{ $row->$field }}
                        @endif
                    </td>
                    @endforeach
                    @if($resource === "groupes")
                    <td>
                        @php($conds = \Illuminate\Support\Facades\DB::table('group_study_conditions')->where('student_group_id', $row->id)->get())
                        @if($conds->isEmpty())
                            <span class="badge badge-info">Tous les jours</span>
                        @else
                            @foreach($conds as $c)
                                <span class="badge badge-success" title="Max {{ $c->max_daily_minutes }} min/jour">{{ ['Lu','Ma','Me','Je','Ve','Sa','Di'][$c->day_of_week-1] ?? '' }} {{ intdiv($c->start_minute,60) }}h{{ str_pad($c->start_minute%60,2,'0',STR_PAD_LEFT) }}-{{ intdiv($c->end_minute,60) }}h{{ str_pad($c->end_minute%60,2,'0',STR_PAD_LEFT) }}</span>
                            @endforeach
                        @endif
                        <a href="{{ route('crud.group-conditions', ['groupes', $row->id]) }}" class="btn btn-sm btn-outline-success" title="Conditions"><i class="fas fa-calendar-check"></i></a>
                    </td>
                    @endif
                    <td class="text-right">
                        <a href="{{ route('crud.edit',[$resource,$row->id]) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                        <form class="d-inline" method="post" action="{{ route('crud.destroy',[$resource,$row->id]) }}" onsubmit="return confirm('Supprimer cet enregistrement ?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ count($meta['fields']) + ($resource === "groupes" ? 1 : 0) }}" class="text-center text-muted py-4">
                        Aucune donnée. Cliquez sur « Ajouter ».
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof jQuery.fn.DataTable !== 'undefined') {
        jQuery('#dataTable').DataTable({
            language: {
                sProcessing: "Traitement en cours...",
                sSearch: "Rechercher :",
                sLengthMenu: "Afficher _MENU_ éléments",
                sInfo: "Affichage de l'élément _START_ à _END_ sur _TOTAL_ éléments",
                sInfoEmpty: "Affichage de l'élément 0 à 0 sur 0 élément",
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
