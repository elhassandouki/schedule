@extends('adminlte::page')
@section('title', $semester->name)
@section('content_header')<div class="d-flex justify-content-between"><h1>{{ $semester->name }} <small class="text-muted">Emploi du temps</small></h1><a href="{{ route('dashboard') }}" class="btn btn-default">Retour</a></div>@endsection
@section('content')
@if(session('generation'))<div class="alert alert-success">{{ session('generation') }}</div>@endif
@if(session('unplaced'))<div class="alert alert-warning"><strong>À traiter :</strong><ul class="mb-0">@foreach(session('unplaced') as $item)<li>{{ $item['module'] }} — {{ $item['group'] }} (occurrence {{ $item['occurrence'] }})</li>@endforeach</ul></div>@endif
<div class="card"><div class="card-body table-responsive p-0"><table class="table table-hover"><thead><tr><th>Jour</th><th>Horaire</th><th>Module</th><th>Groupe</th><th>Professeur</th><th>Salle</th></tr></thead><tbody>@forelse($entries as $entry)<tr><td>{{ $entry->day_name }}</td><td>{{ $entry->starts_at }} – {{ $entry->ends_at }}</td><td>{{ $entry->module }}</td><td>{{ $entry->groupe }}</td><td>{{ $entry->professeur }}</td><td>{{ $entry->salle }}</td></tr>@empty<tr><td colspan="6" class="text-center text-muted">Aucune séance placée.</td></tr>@endforelse</tbody></table></div></div>
@endsection
