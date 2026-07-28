@extends('adminlte::page')
@section('title', 'Professeurs')
@section('content_header')<div class="d-flex justify-content-between"><h1>Gestion des professeurs</h1><a href="{{ route('professors.create') }}" class="btn btn-primary">Ajouter un professeur</a></div>@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="card"><div class="card-body table-responsive p-0"><table class="table table-hover"><thead><tr><th>Professeur</th><th>Limite / semaine</th><th>Modules autorisés</th><th></th></tr></thead><tbody>@forelse($professors as $professor)<tr><td>{{ $professor->name }}<br><small>{{ $professor->email }}</small></td><td>{{ $professor->max_weekly_hours ?? 'Sans limite' }}</td><td>{{ $professor->modules->pluck('code')->join(', ') ?: 'Aucun module' }}</td><td><a href="{{ route('professors.edit', $professor) }}" class="btn btn-sm btn-outline-primary">Modifier</a></td></tr>@empty<tr><td colspan="4" class="text-center">Aucun professeur.</td></tr>@endforelse</tbody></table></div>@if($professors->hasPages())<div class="card-footer">{{ $professors->links() }}</div>@endif</div>
@endsection
