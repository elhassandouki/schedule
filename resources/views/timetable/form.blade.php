@extends('layouts.app')

@section('title', $timetableSession->exists ? 'Modifier session' : 'Nouvelle session')
@section('page_title', $timetableSession->exists ? 'Modifier une session' : 'Créer une session')

@section('content')
<div class="card card-primary card-outline"><form method="POST" action="{{ $timetableSession->exists ? route('timetable.update', $timetableSession) : route('timetable.store') }}">
@csrf @if ($timetableSession->exists) @method('PUT') @endif
<div class="card-body"><div class="row">
@foreach (['semester' => 'Semestre', 'module' => 'Module', 'professor' => 'Professeur', 'classroom' => 'Salle', 'studentGroup' => 'Groupe', 'day' => 'Jour', 'timeslot' => 'Créneau'] as $field => $label)
<div class="form-group col-md-6"><label for="{{ $field }}_id">{{ $label }}</label><select name="{{ $field }}_id" id="{{ $field }}_id" class="form-control @error($field . '_id') is-invalid @enderror" required><option value="">-- Sélectionner --</option>
@foreach (${$field . 's'} as $item)<option value="{{ $item->id }}" @selected(old($field . '_id', $timetableSession->{$field . '_id'}) == $item->id)>@if ($field === 'timeslot'){{ $item->starts_at }}-{{ $item->ends_at }}@elseif ($field === 'semester'){{ $item->program->code ?? '' }} — S{{ $item->number }}@else{{ $item->name }}@endif</option>@endforeach
</select>@error($field . '_id')<small class="text-danger">{{ $message }}</small>@enderror</div>
@endforeach
</div></div><div class="card-footer"><button class="btn btn-primary">Enregistrer</button><a href="{{ route('timetable.index') }}" class="btn btn-secondary">Annuler</a></div>
</form></div>
@endsection
