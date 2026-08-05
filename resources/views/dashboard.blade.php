@extends('layouts.app')

@section('title', 'Tableau de bord')
@section('page_title', 'Tableau de bord')

@section('breadcrumb')
    <ol class="breadcrumb float-sm-right">
        <li class="breadcrumb-item active">Accueil</li>
    </ol>
@endsection

@section('content')
    <!-- Statistics Cards -->
    <div class="row">
        @foreach ($counts as $label => $value)
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3>{{ $value }}</h3>
                        <p>{{ ucfirst($label) }}</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-university"></i>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <!-- Generate Timetable Section -->
        @if (in_array(auth()->user()->role, ['super_admin', 'sous_admin', 'chef_departement', 'chef_filiere']))
            <div class="col-md-6">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-magic mr-2"></i>
                            Générer un emploi du temps
                        </h3>
                    </div>

                    <form action="{{ route('schedules.generate') }}" method="post">
                        @csrf

                        <div class="card-body">
                            <div class="form-group">
                                <label for="semester_id">Semestre</label>
                                <select name="semester_id" id="semester_id" class="form-control" required>
                                    <option value="">-- Sélectionner un semestre --</option>
                                    @foreach ($semesters as $semester)
                                        <option value="{{ $semester->id }}">
                                            {{ $semester->program ?? 'N/A' }} — {{ $semester->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('semester_id')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="name">Nom de la version</label>
                                <input 
                                    type="text" 
                                    name="name" 
                                    id="name" 
                                    class="form-control" 
                                    value="Proposition {{ now()->format('d/m/Y H:i') }}" 
                                    required
                                />
                                @error('name')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>

                            <p class="text-muted small mb-0">
                                <i class="fas fa-info-circle"></i>
                                Capacités, types de salles, indisponibilités et conflits sont contrôlés automatiquement.
                            </p>
                        </div>

                        <div class="card-footer bg-light">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-wand-magic-sparkles mr-2"></i>
                                Générer l'emploi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Recent Generations Section -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header border-bottom-0 bg-light">
                    <h3 class="card-title">
                        <i class="fas fa-history mr-2"></i>
                        Générations récentes
                    </h3>
                </div>

                <div class="card-body p-0">
                    @forelse ($schedules as $schedule)
                        <div class="card-text px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <strong>{{ $schedule->name }}</strong>
                                <br/>
                                <small class="text-muted">{{ $schedule->created_at->diffForHumans() }}</small>
                            </div>
                            <div>
                                <span class="badge badge-{{ $schedule->status === 'generated' ? 'success' : 'warning' }}">
                                    {{ ucfirst($schedule->status) }}
                                </span>
                                <a 
                                    href="{{ route('schedules.show', $schedule) }}" 
                                    class="btn btn-sm btn-outline-primary ml-2"
                                    data-toggle="tooltip"
                                    title="Voir les détails"
                                >
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="card-text p-3 text-center text-muted">
                            <i class="fas fa-inbox mr-2"></i>
                            Aucune génération disponible
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Access Section -->
    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-light border-bottom-0">
                    <h3 class="card-title">
                        <i class="fas fa-bolt mr-2"></i>
                        Accès rapide
                    </h3>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 col-sm-6 text-center mb-3">
                            <a href="{{ route('timetable.index') }}" class="btn btn-lg btn-outline-primary btn-block">
                                <i class="fas fa-calendar fa-2x mb-2"></i>
                                <br/>
                                Emploi du temps
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 text-center mb-3">
                            <a href="{{ route('timetable.create') }}" class="btn btn-lg btn-outline-success btn-block">
                                <i class="fas fa-plus fa-2x mb-2"></i>
                                <br/>
                                Nouvelle session
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 text-center mb-3">
                            <a href="{{ route('dashboard') }}" class="btn btn-lg btn-outline-info btn-block">
                                <i class="fas fa-chart-bar fa-2x mb-2"></i>
                                <br/>
                                Qualité
                            </a>
                        </div>
                        <div class="col-md-3 col-sm-6 text-center mb-3">
                            <a href="{{ route('dashboard') }}" class="btn btn-lg btn-outline-warning btn-block">
                                <i class="fas fa-cog fa-2x mb-2"></i>
                                <br/>
                                Paramètres
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form for logout (hidden) -->
    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
        @csrf
    </form>
@endsection
