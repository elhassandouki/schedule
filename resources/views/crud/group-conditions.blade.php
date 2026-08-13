@extends('adminlte::page')
@section('title', "Conditions d'étude — {$group->name}")
@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1" style="background:transparent; padding:0;">
                <li class="breadcrumb-item"><a href="{{ route('crud.index', 'groupes') }}">Groupes d’étudiants</a></li>
                <li class="breadcrumb-item active">{{ $group->name }}</li>
            </ol>
        </nav>
        <h1>Conditions d'étude — {{ $group->name }}</h1>
    </div>
    <a href="{{ route('crud.index', 'groupes') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Retour aux groupes</a>
</div>
@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
<div class="row">
    <div class="col-md-7">
        <div class="card"><div class="card-header"><h3 class="card-title">Jours autorisés</h3></div>
        <div class="card-body table-responsive p-0">
        @if($rows->isEmpty())<p class="p-3 mb-0 text-muted">Aucune condition définie : <strong>tous les jours (lundi–dimanche, journée entière)</strong> sont considérés autorisés pour ce groupe.</p>
        @else
        <table class="table table-hover mb-0"><thead><tr><th>Jour</th><th>Début</th><th>Fin</th><th>Max minutes / jour</th><th></th></tr></thead><tbody>
        @forelse($rows as $r)
        <tr><td>{{ ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'][$r->day_of_week-1] ?? 'Jour '.$r->day_of_week }}</td>
            <td>{{ intdiv($r->start_minute,60) }}h{{ str_pad($r->start_minute%60,2,'0',STR_PAD_LEFT) }}</td>
            <td>{{ intdiv($r->end_minute,60) }}h{{ str_pad($r->end_minute%60,2,'0',STR_PAD_LEFT) }}</td>
            <td>{{ $r->max_daily_minutes }} min</td>
            <td><a href="{{ route('crud.group-conditions', ['groupes', $group->id]) }}?edit={{ $r->id }}" class="btn btn-sm btn-outline-primary mr-1"><i class="fas fa-edit"></i></a><form method="post" action="{{ route('crud.group-conditions.destroy', ['groupes', $group->id, $r->id]) }}" onsubmit="return confirm('Supprimer cette condition ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form></td></tr>
        @empty
        <tr><td colspan="5" class="text-center">Aucune condition.</td></tr>
        @endforelse
        </tbody></table>
        <p class="small text-muted p-3 mb-0">Les jours non listés sont considérés <strong>non autorisés</strong> (liste blanche) dès qu'au moins une condition est définie. Le maximum de minutes par jour limite les heures de cours cumulées sur ce jour.</p>
        @endif
        </div></div>
    </div>
    <div class="col-md-5">
        <div class="card"><div class="card-header"><h3 class="card-title">Ajouter / modifier une condition</h3></div>
        <div class="card-body">
        @php $editing = request()->has('edit') ? $rows->firstWhere('id', (int) request()->query('edit')) : null; @endphp
        <form method="post" action="{{ route('crud.group-conditions.store', ['groupes', $group->id]) }}">@csrf
            @if($editing)<input type="hidden" name="condition_id" value="{{ $editing->id }}">@endif
            <div class="form-group"><label>Jour</label>
                <select name="day_of_week" class="form-control" required>
                @foreach(['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'] as $i => $d)
                    <option value="{{ $i+1 }}" {{ $editing && $editing->day_of_week === $i+1 ? 'selected' : '' }}>{{ $d }}</option>
                @endforeach
                </select>
            </div>
            <div class="form-group"><label>Heure de début</label>
                <select name="start_minute" class="form-control" required>
                @for($h=0;$h<24;$h++)@for($m=0;$m<60;$m+=15)
                    <option value="{{ $h*60+$m }}" {{ $editing && $editing->start_minute === $h*60+$m ? 'selected' : '' }}>{{ $h }}h{{ str_pad($m,2,'0',STR_PAD_LEFT) }}</option>
                @endfor @endfor
                </select>
            </div>
            <div class="form-group"><label>Heure de fin</label>
                <select name="end_minute" class="form-control" required>
                @for($h=0;$h<24;$h++)@for($m=0;$m<60;$m+=15)
                    <option value="{{ $h*60+$m }}" {{ ($editing && $editing->end_minute === $h*60+$m) || (!$editing && $h*60+$m==1020) ? 'selected' : '' }}>{{ $h }}h{{ str_pad($m,2,'0',STR_PAD_LEFT) }}</option>
                @endfor @endfor
                </select>
            </div>
            <div class="form-group"><label>Maximum de minutes de cours par jour</label>
                <input type="number" name="max_daily_minutes" value="{{ $editing ? $editing->max_daily_minutes : 360 }}" min="0" max="1440" class="form-control" required>
                <small class="text-muted">Ex : 360 = 6 heures de cours maximum sur ce jour.</small>
            </div>
            <button type="submit" class="btn btn-primary">{{ $editing ? 'Mettre à jour' : 'Enregistrer' }}</button>
            @if($editing)<a href="{{ route('crud.group-conditions', ['groupes', $group->id]) }}" class="btn btn-outline-secondary">Annuler</a>@endif
        </form>
        <p class="small text-muted mt-3 mb-0">Si le jour existe déjà, sa condition est remplacée.</p>
        </div></div>
    </div>
</div>
@endsection
