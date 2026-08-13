@extends('adminlte::page')
@section('title', "Disponibilités — {$professor->name}")
@section('content_header')
<div class="d-flex justify-content-between align-items-center">
    <h1>Disponibilités de {{ $professor->name }}</h1>
    <a href="{{ route('professors.index') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Retour aux professeurs</a>
</div>
@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
<div class="row">
    <div class="col-md-7">
        <div class="card"><div class="card-header"><h3 class="card-title">Jours et horaires définis</h3></div>
        <div class="card-body table-responsive p-0">
        @if(!$defined)<p class="p-3 mb-0 text-muted">Aucune disponibilité définie : <strong>tous les jours (lundi–dimanche, journée entière)</strong> sont considérés disponibles pour la génération.</p>
        @else
        <table class="table table-hover mb-0"><thead><tr><th>Jour</th><th>Début</th><th>Fin</th><th>Statut</th><th></th></tr></thead><tbody>
        @forelse($rows as $r)
        <tr><td>{{ ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'][$r->day_of_week-1] ?? 'Jour '.$r->day_of_week }}</td>
            <td>{{ intdiv($r->start_minute,60) }}h{{ str_pad($r->start_minute%60,2,'0',STR_PAD_LEFT) }}</td>
            <td>{{ intdiv($r->end_minute,60) }}h{{ str_pad($r->end_minute%60,2,'0',STR_PAD_LEFT) }}</td>
            <td><span class="badge badge-{{ $r->available?'success':'secondary' }}">{{ $r->available?'Disponible':'Indisponible' }}</span></td>
            <td><form method="post" action="{{ route('professors.availabilities.destroy', [$professor->id, $r->id]) }}" onsubmit="return confirm('Supprimer cette disponibilité ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button></form></td></tr>
        @empty
        <tr><td colspan="5" class="text-center">Aucune disponibilité.</td></tr>
        @endforelse
        </tbody></table>
        <p class="small text-muted p-3 mb-0">Les jours non listés sont considérés <strong>non disponibles</strong> (liste blanche) dès qu'au moins une disponibilité est définie.</p>
        @endif
        </div></div>
    </div>
    <div class="col-md-5">
        <div class="card"><div class="card-header"><h3 class="card-title">Ajouter / modifier une disponibilité</h3></div>
        <div class="card-body">
        <form method="post" action="{{ route('professors.availabilities.update', $professor) }}">@csrf
            <div class="form-group"><label>Jour</label>
                <select name="day_of_week" class="form-control" required>
                @foreach(['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'] as $i => $d)
                    <option value="{{ $i+1 }}">{{ $d }}</option>
                @endforeach
                </select>
            </div>
            <div class="form-group"><label>Heure de début</label>
                <select name="start_minute" class="form-control" required>
                @for($h=0;$h<24;$h++)@for($m=0;$m<60;$m+=15)
                    <option value="{{ $h*60+$m }}">{{ $h }}h{{ str_pad($m,2,'0',STR_PAD_LEFT) }}</option>
                @endfor @endfor
                </select>
            </div>
            <div class="form-group"><label>Heure de fin</label>
                <select name="end_minute" class="form-control" required>
                @for($h=0;$h<24;$h++)@for($m=0;$m<60;$m+=15)
                    <option value="{{ $h*60+$m }}" {{ $h*60+$m==1020?'selected':'' }}>{{ $h }}h{{ str_pad($m,2,'0',STR_PAD_LEFT) }}</option>
                @endfor @endfor
                </select>
            </div>
            <div class="form-group form-check">
                <input type="checkbox" name="available" value="1" checked class="form-check-input" id="availChk">
                <label class="form-check-label" for="availChk">Disponible</label>
            </div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </form>
        <p class="small text-muted mt-3 mb-0">Si le jour existe déjà, son créneau est remplacé.</p>
        </div></div>
    </div>
</div>
@endsection
