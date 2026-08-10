@extends('layouts.app')

@section('title', $timetableSession->exists ? 'Modifier session' : 'Nouvelle session')
@section('page_title', $timetableSession->exists ? 'Modifier une session' : 'Créer une nouvelle session')

@section('breadcrumb')
    <ol class="breadcrumb float-sm-right">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Accueil</a></li>
        <li class="breadcrumb-item"><a href="{{ route('timetable.index') }}">Emploi du temps</a></li>
        <li class="breadcrumb-item active">
            {{ $timetableSession->exists ? 'Modifier' : 'Créer' }}
        </li>
    </ol>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-8">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-{{ $timetableSession->exists ? 'edit' : 'plus' }} mr-2"></i>
                        {{ $timetableSession->exists ? 'Modifier la session' : 'Créer une nouvelle session' }}
                    </h3>
                </div>

                <form method="POST" action="{{ $timetableSession->exists ? route('timetable.update', $timetableSession) : route('timetable.store') }}">
                    @csrf
                    @if ($timetableSession->exists)
                        @method('PUT')
                    @endif

                    <div class="card-body">
                        <div class="row">
                            @foreach (['semester' => 'Semestre', 'subject' => 'Matière', 'teacher' => 'Professeur', 'classroom' => 'Salle', 'studentGroup' => 'Groupe d\'étudiants', 'day' => 'Jour', 'timeslot' => 'Créneau horaire'] as $field => $label)
                                <div class="form-group col-md-6">
                                    <label for="{{ $field }}_id" class="font-weight-bold">
                                        {{ $label }}
                                    </label>

                                    <select 
                                        name="{{ $field }}_id" 
                                        id="{{ $field }}_id" 
                                        class="form-control @error($field . '_id') is-invalid @enderror" 
                                        required
                                    >
                                        <option value="">-- Sélectionner --</option>

                                        @foreach (${$field . 's'} as $item)
                                            <option 
                                                value="{{ $item->id }}" 
                                                @selected(old($field . '_id', $timetableSession->{$field . '_id'}) == $item->id)
                                            >
                                                @if ($field === 'timeslot')
                                                    {{ $item->starts_at }}-{{ $item->ends_at }}
                                                @elseif ($field === 'semester')
                                                    {{ $item->program->code ?? '' }} — S{{ $item->number }}
                                                @else
                                                    {{ $item->name }}
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>

                                    @error($field . '_id')
                                        <small class="text-danger d-block mt-1">
                                            <i class="fas fa-exclamation-circle mr-1"></i>
                                            {{ $message }}
                                        </small>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="card-footer bg-light">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save mr-2"></i>
                            {{ $timetableSession->exists ? 'Mettre à jour' : 'Créer' }}
                        </button>

                        <a href="{{ route('timetable.index') }}" class="btn btn-secondary">
                            <i class="fas fa-times mr-2"></i>
                            Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-info card-outline">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-info-circle mr-2"></i>
                        Aide
                    </h3>
                </div>

                <div class="card-body text-sm">
                    <p>Remplissez tous les champs pour créer ou modifier une session d'emploi du temps.</p>

                    <p class="mb-0">
                        <strong>Contraintes vérifiées :</strong>
                    </p>
                    <ul class="mb-3">
                        <li>Pas de double-réservation de professeur</li>
                        <li>Pas de double-réservation de salle</li>
                        <li>Pas de double-réservation de groupe</li>
                        <li>Capacité de la salle suffisante</li>
                    </ul>

                    <p class="text-muted mb-0">
                        <i class="fas fa-lightbulb mr-1"></i>
                        Un message d'erreur apparaîtra si une contrainte n'est pas respectée.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
