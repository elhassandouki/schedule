@extends('adminlte::page')
@section('title', ($row?'Modifier ':'Ajouter ').$meta['title'])
@section('content_header')
<h1>
    <i class="{{ $meta['icon'] ?? 'fas fa-plus-circle' }} mr-2"></i>
    {{ $row?'Modifier':'Ajouter' }} — {{ $meta['title'] }}
</h1>
@endsection
@section('breadcrumb')
<ol class="breadcrumb float-sm-right">
    <li class="breadcrumb-item"><a href="{{ route('crud.index', $resource) }}">{{ $meta['title'] }}</a></li>
    <li class="breadcrumb-item active">{{ $row?'Modification':'Ajout' }}</li>
</ol>
@endsection
@section('content')
<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title mb-0 text-white">
            <i class="fas fa-{{ $row ? 'edit' : 'plus' }} mr-2"></i>
            {{ $row?'Modifier':'Ajouter' }} — {{ $meta['title'] }}
        </h3>
    </div>
    <form method="post" action="{{ $row ? route('crud.update',[$resource,$row->id]) : route('crud.store',$resource) }}">
        @csrf @if($row)@method('PUT')@endif
        <div class="card-body">
            @if(session()->has('crude'))<div class="alert alert-danger">{{ session('crude') }}</div>@endif
            <div class="row">
                @foreach($meta['fields'] as $field=>$label)
                <div class="form-group col-md-6">
                    <label for="input-{{ $field }}">{{ $label }}</label>
                    <?php $type=$meta['types'][$field]??'text'; ?>
                    @if($field === 'semester_id' && ($resource === 'groupes' || $resource === 'modules'))
                        {{-- Semestre filtré par la filière choisie (client-side) --}}
                        <select class="form-control @error($field)is-invalid @enderror" name="{{ $field }}" id="select-{{ $field }}" {{ empty($programSemesters) && !old($field,$row->$field??'') ? 'disabled' : '' }}>
                            <option value="">{{ empty($programSemesters) ? 'Choisissez d’abord une filière' : 'Sélectionner' }}</option>
                            @foreach(($programSemesters ?: $choices[$field] ?? []) as $id=>$name)
                                <option value="{{ $id }}" @selected(old($field,$row->$field??'')==$id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    @elseif(isset($choices[$field]))
                        <select class="form-control @error($field)is-invalid @enderror" name="{{ $field }}" id="select-{{ $field }}">
                            <option value="">Sélectionner</option>
                            @foreach($choices[$field] as $id=>$name)
                                <option value="{{ $id }}" @selected(old($field,$row->$field??'')==$id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    @elseif($type==='select')
                        <select class="form-control" name="{{ $field }}" id="select-{{ $field }}">
                            @foreach($meta['options'][$field] as $key=>$name)
                                <option value="{{ $key }}" @selected(old($field,$row->$field??'')===$key)>{{ $name }}</option>
                            @endforeach
                        </select>
                    @elseif($type==='checkbox')
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="{{ $field }}" name="{{ $field }}" value="1" @checked(old($field,$row->$field??false))>
                            <label class="custom-control-label" for="{{ $field }}">Oui</label>
                        </div>
                    @else
                        <div class="input-group">
                            <input class="form-control @error($field)is-invalid @enderror" id="input-{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ $type==='password'?'':old($field,$row->$field??'') }}" placeholder="{{ $label }}" {{ $field==='password'&&$row?'':'required' }}>
                            <div class="input-group-append">
                                <span class="input-group-text"><i class="fas fa-{{ $type==='password' ? 'key' : ($type==='number' ? 'hashtag' : 'pencil-alt') }}"></i></span>
                            </div>
                        </div>
                    @endif
                    @error($field)<span class="text-danger small">{{ $message }}</span>@enderror
                </div>
                @endforeach
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-1"></i> Enregistrer
            </button>
            <a href="{{ route('crud.index',$resource) }}" class="btn btn-default">
                <i class="fas fa-times mr-1"></i> Annuler
            </a>
        </div>
    </form>
</div>

@if($resource === 'groupes' || $resource === 'modules')
@push('js')
<script>
(function () {
    // Données des semestres : programme_id => [{id, name}]
    var semestersByProgram = {};
    @php
        $semesters = \Illuminate\Support\Facades\DB::table('semesters')
            ->select('id', 'program_id', 'name', 'number')
            ->orderBy('program_id')
            ->orderBy('number')
            ->get();
    @endphp
    @foreach($semesters as $s)
    (semestersByProgram[{{ $s->program_id }}] = semestersByProgram[{{ $s->program_id }}] || []).push({ id: {{ $s->id }}, name: "{!! addslashes($s->name) !!}" });
    @endforeach

    var programSelect = document.getElementById('select-program_id');
    var semesterSelect = document.getElementById('select-semester_id');
    if (!programSelect || !semesterSelect) return;

    function rebuildSemesters() {
        var pid = programSelect.value;
        var current = semesterSelect.value;
        var options = (pid && semestersByProgram[pid]) ? semestersByProgram[pid] : [];
        semesterSelect.innerHTML = '<option value="">' + (options.length ? 'Sélectionner' : 'Aucun semestre pour cette filière') + '</option>';
        options.forEach(function (s) {
            var opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;
            if (String(s.id) === String(current)) opt.selected = true;
            semesterSelect.appendChild(opt);
        });
        semesterSelect.disabled = options.length === 0;
    }

    // Pré-sélection de la filière depuis le querystring (?program_id=...)
    var qs = new URLSearchParams(window.location.search);
    var qsProgram = qs.get('program_id');
    if (qsProgram && !programSelect.value) {
        for (var i = 0; i < programSelect.options.length; i++) {
            if (String(programSelect.options[i].value) === String(qsProgram)) {
                programSelect.value = qsProgram;
                break;
            }
        }
    }
    rebuildSemesters();
    programSelect.addEventListener('change', rebuildSemesters);
})();
</script>
@endpush
@endif
@endsection
