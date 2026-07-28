@extends('adminlte::page')
@section('title', $timetableSession->exists ? 'Edit session' : 'New session')
@section('content_header')<h1>{{ $timetableSession->exists ? 'Edit session' : 'New timetable session' }}</h1>@endsection
@section('content')
<div class="card card-primary"><form method="POST" action="{{ $timetableSession->exists ? route('timetable.update', $timetableSession) : route('timetable.store') }}">@csrf @if($timetableSession->exists) @method('PUT') @endif
<div class="card-body row">
@foreach(['subject'=>'Subject','teacher'=>'Teacher','classroom'=>'Classroom','section'=>'Section','day'=>'Day','timeslot'=>'Timeslot'] as $field=>$label)
<div class="form-group col-md-6"><label>{{ $label }}</label><select class="form-control @error($field.'_id') is-invalid @enderror" name="{{ $field }}_id" required><option value="">Select</option>@foreach(${$field.'s'} as $item)<option value="{{ $item->id }}" @selected(old($field.'_id', $timetableSession->{$field.'_id'}) == $item->id)>{{ $field === 'timeslot' ? $item->name.' ('.$item->starts_at.'-'.$item->ends_at.')' : $item->name }}</option>@endforeach</select>@error($field.'_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
@endforeach
</div><div class="card-footer"><button class="btn btn-primary">Save</button> <a class="btn btn-default" href="{{ route('timetable.index') }}">Cancel</a></div></form></div>
@endsection
