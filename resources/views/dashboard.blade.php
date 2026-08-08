@extends('layouts.app')

@section('title', 'Tableau de bord')
@section('page_title', 'Tableau de bord')

@section('breadcrumb')
    <ol class="breadcrumb float-sm-right">
        <li class="breadcrumb-item active">Accueil</li>
    </ol>
@endsection

@section('content')
    <!-- Hero: Key Statistics -->
    <div class="row mb-4">
        @forelse ($counts as $label => $value)
            <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
                <div class="card h-100 border-0 shadow-sm stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <p class="text-muted small text-uppercase mb-1">{{ ucfirst($label) }}</p>
                                <h3 class="mb-0 text-primary">{{ $value }}</h3>
                            </div>
                            <i class="fas fa-university text-muted fa-lg opacity-5"></i>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <p class="text-muted text-center">Aucune donnée disponible</p>
            </div>
        @endforelse
    </div>

    <!-- Generate & Recent -->
    <div class="row mt-4">
        <!-- Generate Timetable Section -->
        @if (in_array(auth()->user()->role, ['super_admin', 'sous_admin', 'chef_departement', 'chef_filiere']))
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light border-0">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-magic text-primary mr-2"></i>
                            Générer un emploi du temps
                        </h4>
                    </div>

                    <form action="{{ route('timetable.generate') }}" method="post">
                        @csrf

                        <div class="card-body">
                            <div class="form-group">
                                <label for="semester_id" class="font-weight-600">Semestre</label>
                                <select name="semester_id" id="semester_id" class="form-control" required>
                                    <option value="">-- Sélectionner un semestre --</option>
                                    @foreach ($semesters as $semester)
                                        <option value="{{ $semester->id }}">
                                            {{ $semester->program ?? 'N/A' }} — {{ $semester->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('semester_id')
                                    <small class="text-danger d-block mt-2">{{ $message }}</small>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="name" class="font-weight-600">Nom de la version</label>
                                <input 
                                    type="text" 
                                    name="name" 
                                    id="name" 
                                    class="form-control" 
                                    value="Proposition {{ now()->format('d/m/Y H:i') }}" 
                                    required
                                />
                                @error('name')
                                    <small class="text-danger d-block mt-2">{{ $message }}</small>
                                @enderror
                            </div>

                            <p class="text-muted small mb-0">
                                <i class="fas fa-info-circle"></i>
                                Capacités, types de salles, indisponibilités et conflits sont contrôlés automatiquement.
                            </p>
                        </div>

                        <div class="card-footer bg-light border-0">
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
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light border-0">
                    <h4 class="card-title mb-0">
                        <i class="fas fa-history text-primary mr-2"></i>
                        Générations récentes
                    </h4>
                </div>

                <div class="card-body p-0">
                    @forelse ($schedules as $schedule)
                        <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
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
                                    href="{{ route('timetable.show', $schedule->semester_id) }}" 
                                    class="btn btn-sm btn-outline-primary ml-2"
                                    title="Voir l'emploi du temps"
                                >
                                    <i class="fas fa-eye"></i>
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="p-3 text-center text-muted">
                            <i class="fas fa-inbox mr-2"></i>
                            Aucune génération disponible
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Setup Wizard Section -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light border-0">
                    <h4 class="card-title mb-0">
                        <i class="fas fa-route text-primary mr-2"></i>
                        Configuration guidée
                        @if ($wizard['ready'])
                            <span class="badge badge-success ml-2"><i class="fas fa-check"></i> Données prêtes</span>
                        @elseif ($wizard['next'])
                            <span class="badge badge-warning ml-2">Étape suivante : {{ $wizard['next']['label'] }}</span>
                        @endif
                    </h4>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Créez vos données dans l’ordre logique : chaque étape dépend de la précédente.
                        Si une étape est bloquée, complétez d’abord celle qui précède.
                    </p>
                    <div class="row">
                        @foreach ($wizard['steps'] as $index => $step)
                        <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-3">
                            <div class="card h-100 border {{ $step['done'] ? 'border-success' : ($wizard['next'] && $wizard['next']['key'] === $step['key'] ? 'border-warning' : 'border-light') }}">
                                <div class="card-body p-3 text-center">
                                    <span class="badge {{ $step['done'] ? 'badge-success' : 'badge-secondary' }} mb-2">
                                        {{ $step['done'] ? '✔' : ($index + 1) }}
                                    </span>
                                    <h6 class="mb-1" style="font-size:.9rem">{{ $step['label'] }}</h6>
                                    <p class="text-muted small mb-2" style="font-size:.75rem">{{ $step['desc'] }}</p>
                                    @if ($wizard['next'] && $wizard['next']['key'] === $step['key'])
                                        <a href="{{ route('crud.create', $step['resource']) }}" class="btn btn-xs btn-warning btn-sm w-100">
                                            <i class="fas fa-plus"></i> Commencer
                                        </a>
                                    @elseif ($step['done'])
                                        <a href="{{ route('crud.index', $step['resource']) }}" class="btn btn-xs btn-outline-success btn-sm w-100">
                                            <i class="fas fa-list"></i> Voir ({{ $step['count'] }})
                                        </a>
                                    @else
                                        <button class="btn btn-xs btn-outline-secondary btn-sm w-100" disabled title="Complétez l’étape précédente d’abord">
                                            <i class="fas fa-lock"></i> Bloqué
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @if ($wizard['ready'])
                    <div class="alert alert-success mb-0 mt-2">
                        <i class="fas fa-check-circle mr-2"></i>
                        Toutes les données de référence sont en place. Vous pouvez maintenant créer les <strong>matières</strong> puis <strong>générer votre premier emploi du temps</strong> depuis le formulaire ci-dessus.
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Section -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light border-0">
                    <h4 class="card-title mb-0">
                        <i class="fas fa-bolt text-primary mr-2"></i>
                        Actions rapides
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                            <a href="{{ route('crud.index', 'semesters') }}" class="text-decoration-none">
                                <div class="quick-action-card h-100">
                                    <i class="fas fa-calendar fa-2x text-primary mb-3"></i>
                                    <h6 class="font-weight-600">Emploi du temps</h6>
                                    <p class="text-muted small">Voir les semesters</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                            <a href="/timetable/sessions/create" class="text-decoration-none">
                                <div class="quick-action-card h-100">
                                    <i class="fas fa-plus-circle fa-2x text-success mb-3"></i>
                                    <h6 class="font-weight-600">Nouvelle session</h6>
                                    <p class="text-muted small">Ajouter une séance</p>
                                </div>
                            </a>
                        </div>
                        @if (!empty($semesters) && $semesters->first())
                        <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                            <a href="{{ route('crud.index', 'semesters') }}" class="text-decoration-none">
                                <div class="quick-action-card h-100">
                                    <i class="fas fa-chart-bar fa-2x text-info mb-3"></i>
                                    <h6 class="font-weight-600">Gérer semesters</h6>
                                    <p class="text-muted small">Analyse détaillée</p>
                                </div>
                            </a>
                        </div>
                        @endif
                        <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                            <a href="{{ route('crud.index', 'semesters') }}" class="text-decoration-none">
                                <div class="quick-action-card h-100">
                                    <i class="fas fa-cog fa-2x text-warning mb-3"></i>
                                    <h6 class="font-weight-600">Paramètres</h6>
                                    <p class="text-muted small">Gérer les données</p>
                                </div>
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
