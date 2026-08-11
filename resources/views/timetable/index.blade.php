@extends('layouts.app')

@section('title', 'Emploi du temps')
@section('page_title', 'Emploi du temps')

@section('breadcrumb')
    <ol class="breadcrumb float-sm-right">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Accueil</a></li>
        <li class="breadcrumb-item active">Emploi du temps</li>
    </ol>
@endsection

@section('content')
    <!-- Filter Section -->
    <div class="filter-section">
        <form class="row" method="get">
            <div class="col-md-2 mb-2">
                <select name="program_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Tous les programmes</option>
                    @foreach ($programs as $item)
                        <option value="{{ $item->id }}" @selected(request('program_id') == $item->id)>
                            {{ $item->code }} — {{ $item->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2 mb-2">
                <select name="semester_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Tous les semestres</option>
                    @foreach ($semesters as $item)
                        <option value="{{ $item->id }}" @selected(request('semester_id') == $item->id)>
                            {{ $item->program->code ?? '' }} S{{ $item->number }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2 mb-2">
                <select name="student_group_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Tous les groupes</option>
                    @foreach ($studentGroups as $item)
                        <option value="{{ $item->id }}" @selected(request('student_group_id') == $item->id)>
                            {{ $item->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2 mb-2">
                <select name="professor_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Tous les professeurs</option>
                    @foreach ($professors as $item)
                        <option value="{{ $item->id }}" @selected(request('professor_id') == $item->id)>
                            {{ $item->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <div class="btn-group btn-block">
                    <a href="{{ route('timetable.index') }}" class="btn btn-secondary">
                        <i class="fas fa-undo mr-1"></i>
                        Réinitialiser
                    </a>
                    <a href="{{ route('timetable.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus mr-1"></i>
                        Nouvelle session
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Timetable Grid -->
    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-calendar-grid-3 mr-2"></i>
                Grille de l'emploi du temps
            </h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>

        <div class="card-body p-0 table-responsive">
            @forelse ($timeslots as $slot)
                <table class="table table-bordered mb-0">
                    <thead>
                        <tr class="bg-light">
                            <th colspan="{{ $days->count() + 1 }}" class="text-center font-weight-bold">
                                <i class="fas fa-clock mr-2"></i>
                                {{ $slot->starts_at }} — {{ $slot->ends_at }}
                            </th>
                        </tr>
                        <tr>
                            <th style="width: 100px;">Créneau</th>
                            @foreach ($days as $day)
                                <th class="text-center">{{ $day->name }}</th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td><small class="text-muted">{{ $slot->starts_at }}–{{ $slot->ends_at }}</small></td>

                            @foreach ($days as $day)
                                <td>
                                    @foreach ($sessions->where('timeslot_id', $slot->id)->where('day_id', $day->id) as $session)
                                        <div class="card card-sm mb-2 border-left border-primary">
                                            <div class="card-body p-2">
                                                <p class="mb-1">
                                                    <strong class="text-primary">{{ $session->module->name }}</strong>
                                                </p>
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-chalkboard-user"></i>
                                                    {{ $session->professor->name }}
                                                </small>
                                                <small class="text-muted d-block">
                                                    <i class="fas fa-door-open"></i>
                                                    {{ $session->classroom->name }}
                                                </small>
                                                <small class="text-muted d-block mb-2">
                                                    <i class="fas fa-users"></i>
                                                    {{ $session->studentGroup->name }}
                                                </small>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('timetable.edit', $session) }}" 
                                                       class="btn btn-outline-primary btn-sm"
                                                       data-toggle="tooltip"
                                                       title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form action="{{ route('timetable.destroy', $session) }}" method="POST" 
                                                          style="display: inline;">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" 
                                                                class="btn btn-outline-danger btn-sm"
                                                                data-toggle="tooltip"
                                                                title="Supprimer"
                                                                onclick="return confirm('Êtes-vous sûr?')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach

                                    @if ($sessions->where('timeslot_id', $slot->id)->where('day_id', $day->id)->isEmpty())
                                        <p class="text-muted text-center small mb-0">—</p>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            @empty
                <div class="alert alert-info m-0 rounded-0">
                    <i class="fas fa-info-circle mr-2"></i>
                    Créez des créneaux horaires pour afficher la grille d'emploi du temps.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Statistics -->
    <div class="row mt-3">
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-info"><i class="fas fa-book"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Sessions totales</span>
                    <span class="info-box-number">{{ $sessions->count() }}</span>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-success"><i class="fas fa-chalkboard-user"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Professeurs</span>
                    <span class="info-box-number">{{ $professors->count() }}</span>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-warning"><i class="fas fa-door-open"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Salles</span>
                    <span class="info-box-number">{{ $classrooms->count() }}</span>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-primary"><i class="fas fa-users"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Groupes</span>
                    <span class="info-box-number">{{ $studentGroups->count() }}</span>
                </div>
            </div>
        </div>
    </div>
@endsection
