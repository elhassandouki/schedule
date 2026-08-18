@extends('layouts.app')

@section('title', 'Tableau de bord')
@section('page_title', 'Tableau de bord')

@section('breadcrumb')
    <ol class="breadcrumb float-sm-right">
        <li class="breadcrumb-item active">Accueil</li>
    </ol>
@endsection

@section('content')
    <!-- Hero: Key Statistics (AdminLTE small-boxes) -->
    @php
        $statMap = [
            'filières'   => ['icon' => 'fas fa-graduation-cap', 'bg' => 'bg-primary'],
            'semestres'  => ['icon' => 'fas fa-layer-group', 'bg' => 'bg-success'],
            'groupes'    => ['icon' => 'fas fa-users', 'bg' => 'bg-info'],
            'professeurs' => ['icon' => 'fas fa-chalkboard-teacher', 'bg' => 'bg-warning'],
        ];
    @endphp
    <div class="row">
    @forelse ($counts as $label => $value)
        <div class="col-lg-3 col-md-6 col-sm-12 mb-3">
            <div class="small-box {{ $statMap[$label]['bg'] ?? 'bg-primary' }}">
                <div class="inner">
                    <h3>{{ $value }}</h3>
                    <p>{{ ucfirst($label) }}</p>
                </div>
                <div class="icon">
                    <i class="{{ $statMap[$label]['icon'] ?? 'fas fa-university' }}"></i>
                </div>
                <a href="{{ route('crud.index', 'semestres') }}" class="small-box-footer">
                    Voir les détails <i class="fas fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
    @empty
        <div class="col-12">
            <p class="text-muted text-center">Aucune donnée disponible</p>
        </div>
    @endforelse
    </div>

    <!-- État des emplois du temps (statistiques décisionnelles) -->
    @if (isset($timetable_status) && $timetable_status['totals']['semesters'] > 0)
    @php
        $totals = $timetable_status['totals'];
    @endphp
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary">
                    <h3 class="card-title mb-0 text-white">
                        <i class="fas fa-chart-pie mr-2"></i>État des emplois du temps
                        <span class="badge badge-light ml-2">{{ $totals['semesters'] }} semestre(s)</span>
                    </h3>
                </div>
                <div class="card-body">
                    <!-- Totaux rapides -->
                    <div class="row text-center mb-4">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="p-3 bg-success bg-opacity-10 rounded" style="background:rgba(28,187,140,.08)">
                                <h4 class="mb-0 text-success">{{ $totals['complete'] }}</h4>
                                <small class="text-muted">Emplois complets</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="p-3 rounded" style="background:rgba(255,193,7,.08)">
                                <h4 class="mb-0 text-warning">{{ $totals['partial'] }}</h4>
                                <small class="text-muted">À compléter</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="p-3 rounded" style="background:rgba(220,53,69,.08)">
                                <h4 class="mb-0 text-danger">{{ $totals['empty'] }}</h4>
                                <small class="text-muted">Pas encore générés</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="p-3 rounded" style="background:rgba(108,117,125,.08)">
                                <h4 class="mb-0 text-secondary">{{ $totals['missing_prof_modules'] }}</h4>
                                <small class="text-muted">Modules sans professeur</small>
                            </div>
                        </div>
                    </div>

                    @if ($totals['missing_prof_modules'] > 0)
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>{{ $totals['missing_prof_modules'] }} module(s) n'ont pas de professeur assigné.</strong>
                            Ils seront ignorés lors de la génération. Assigne un professeur dans la gestion des modules.
                        </div>
                    @endif

                    <!-- Détail par semestre -->
                    @foreach ($timetable_status['items'] as $item)
                        @php
                            $badgeClass = $item['state'] === 'complete' ? 'badge-success' : ($item['state'] === 'partial' ? 'badge-warning' : 'badge-secondary');
                            $stateLabel = $item['state'] === 'complete' ? 'Complet' : ($item['state'] === 'partial' ? 'Partiel' : 'À générer');
                            $barClass = $item['state'] === 'complete' ? 'bg-success' : ($item['state'] === 'partial' ? 'bg-warning' : 'bg-secondary');
                            $hoursPlaced = intdiv($item['placed_minutes'], 60);
                            $minutesPlaced = $item['placed_minutes'] % 60;
                            $hoursExpected = intdiv($item['expected_minutes'], 60);
                            $minutesExpected = $item['expected_minutes'] % 60;
                        @endphp
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap">
                                <h6 class="mb-1">
                                    <i class="fas fa-graduation-cap text-primary"></i>
                                    {{ $item['semester']->program_name }} — {{ $item['semester']->semester_name }}
                                    <span class="badge {{ $badgeClass }} ml-2">{{ $stateLabel }}</span>
                                </h6>
                                <div class="small text-muted">
                                    {{ $item['coverage'] }}% du quota horaire ·
                                    {{ $hoursPlaced }}h{{ $minutesPlaced ? '0'.(string)$minutesPlaced : '' }} placées
                                    sur {{ $hoursExpected }}h{{ $minutesExpected ? '0'.(string)$minutesExpected : '' }} ·
                                    {{ $item['module_count'] }} modules ·
                                    {{ $item['used_rooms'] }}/{{ $item['total_rooms'] }} salles
                                    @if ($item['last_generation'])
                                        · dernière génération : {{ $item['last_generation']->created_at->format('d/m/Y H:i') }}
                                        @if ($item['last_generation']->status === 'partial')
                                            <span class="badge badge-warning">partielle ({{ $item['last_generation']->skipped_sessions_count }} ignorées)</span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                            <div class="progress mb-2" style="height: 10px">
                                <div class="progress-bar {{ $barClass }}" style="width: {{ $item['coverage'] }}%"></div>
                            </div>
                            @if ($item['missing_prof_count'] > 0)
                                <div class="small text-warning">
                                    <i class="fas fa-user-slash"></i> Sans professeur :
                                    @foreach ($item['missing_prof_modules'] as $mq)
                                        <strong>{{ $mq->module_name }}</strong>{{ !$loop->last ? ',' : '' }}
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Generate & Recent -->
    <div class="row mt-4">
        <!-- Generate Timetable Section -->
        @if (in_array(auth()->user()->role, ['super_admin', 'sous_admin', 'chef_departement', 'chef_filiere']))
            <div class="col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary">
                        <h3 class="card-title mb-0 text-white">
                            <i class="fas fa-magic mr-2"></i>
                            Générer un emploi du temps
                        </h3>
                    </div>

                                <form action="{{ route('timetable.generate') }}" method="post" id="generate-form">
                        @csrf

                        <div class="card-body">
                            <div class="form-group">
                                <label for="program_id" class="font-weight-600">Filière</label>
                                <select name="program_id" id="program_id" class="form-control">
                                    <option value="">-- Sélectionner une filière --</option>
                                    @foreach ($programs as $program)
                                        <option value="{{ $program->id }}">{{ $program->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="semester_id" class="font-weight-600">Semestre</label>
                                <select name="semester_id" id="semester_id" class="form-control" required>
                                    <option value="">-- Sélectionner un semestre --</option>
                                    @foreach ($semesters as $semester)
                                        <option value="{{ $semester->id }}" data-program-id="{{ $semester->program_id }}">
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

                            @if ($errors->has('generation'))
                                <div class="alert alert-danger mb-0 mt-3">
                                    <i class="fas fa-exclamation-circle mr-2"></i>
                                    {{ $errors->first('generation') }}
                                </div>
                            @endif

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

        <a id="recent-generations"></a>
    <!-- Recent Generations Section -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                    <div class="card-header bg-primary">
                        <h3 class="card-title mb-0 text-white">
                            <i class="fas fa-history mr-2"></i>
                            Générations récentes
                        </h3>
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
                                <form method="post" action="{{ route('timetable.generate.destroy', $schedule->id) }}" class="d-inline ml-1" onsubmit="return confirm('Supprimer cette génération et toutes ses séances ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer cette génération">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
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
                    <div class="card-header bg-primary">
                        <h3 class="card-title mb-0 text-white">
                            <i class="fas fa-route mr-2"></i>
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
                                <div class="card-body p-3 text-center wizard-step">
                                    <i class="{{ $step['icon'] ?? 'fas fa-check' }} fa-2x {{ $step['done'] ? 'text-success' : 'text-muted' }} mb-2 d-block"></i>
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
                    <div class="card-header bg-primary">
                        <h3 class="card-title mb-0 text-white">
                            <i class="fas fa-bolt mr-2"></i>
                            Actions rapides
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                            <a href="{{ route('crud.index', 'semestres') }}" class="text-decoration-none">
                                <div class="quick-action-card h-100">
                                    <i class="fas fa-layer-group fa-2x text-primary mb-3"></i>
                                    <h6 class="font-weight-600">Semestres</h6>
                                    <p class="text-muted small">Voir et gérer les semestres</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                            <a href="{{ route('timetable.create') }}" class="text-decoration-none">
                                <div class="quick-action-card h-100">
                                    <i class="fas fa-plus-circle fa-2x text-success mb-3"></i>
                                    <h6 class="font-weight-600">Nouvelle séance</h6>
                                    <p class="text-muted small">Ajouter une session manuellement</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                            <a href="{{ route('crud.index', 'professeurs') }}" class="text-decoration-none">
                                <div class="quick-action-card h-100">
                                    <i class="fas fa-chalkboard-teacher fa-2x text-info mb-3"></i>
                                    <h6 class="font-weight-600">Enseignants</h6>
                                    <p class="text-muted small">Disponibilités et modules</p>
                                </div>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-6 col-sm-6 mb-3">
                            <a href="{{ route('crud.index', 'modules') }}" class="text-decoration-none">
                                <div class="quick-action-card h-100">
                                    <i class="fas fa-book-open fa-2x text-warning mb-3"></i>
                                    <h6 class="font-weight-600">Modules</h6>
                                    <p class="text-muted small">Matières et volumes horaires</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics & Charts Section -->
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title mb-0 text-white">
                        <i class="fas fa-chart-line mr-2"></i>
                        Statistiques & État d’avancement
                    </h3>
                    <div class="card-tools">
                        <span class="badge badge-light"><i class="fas fa-chart-pie mr-1"></i>Temps réel</span>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Progress bars per entity -->
                    @php
                        $progressItems = [
                            ['label' => 'Jours de la semaine', 'icon' => 'fas fa-calendar-day', 'current' => $reference['days'], 'target' => 7, 'color' => 'bg-primary', 'route' => route('crud.index', 'days')],
                            ['label' => 'Créneaux horaires', 'icon' => 'fas fa-clock', 'current' => $reference['timeslots'], 'target' => max(8, $reference['timeslots']), 'color' => 'bg-success', 'route' => route('crud.index', 'timeslots')],
                            ['label' => 'Années universitaires', 'icon' => 'fas fa-calendar-check', 'current' => $reference['years'], 'target' => 1, 'color' => 'bg-info', 'route' => route('crud.index', 'annees')],
                            ['label' => 'Départements', 'icon' => 'fas fa-building', 'current' => $reference['departments'], 'target' => max(1, $reference['departments']), 'color' => 'bg-warning', 'route' => route('crud.index', 'departements')],
                            ['label' => 'Filières', 'icon' => 'fas fa-graduation-cap', 'current' => $reference['programs'], 'target' => max(1, $reference['programs']), 'color' => 'bg-danger', 'route' => route('crud.index', 'filieres')],
                            ['label' => 'Semestres', 'icon' => 'fas fa-layer-group', 'current' => $reference['semesters'], 'target' => max(1, $reference['semesters']), 'color' => 'bg-indigo', 'route' => route('crud.index', 'semestres')],
                            ['label' => 'Salles', 'icon' => 'fas fa-door-open', 'current' => $reference['classrooms'], 'target' => max(1, $reference['classrooms']), 'color' => 'bg-teal', 'route' => route('crud.index', 'salles')],
                            ['label' => 'Modules', 'icon' => 'fas fa-book-open', 'current' => $reference['modules'], 'target' => max(1, $reference['modules']), 'color' => 'bg-maroon', 'route' => route('crud.index', 'modules')],
                            ['label' => "Groupes d'étudiants", 'icon' => 'fas fa-users', 'current' => $reference['groupes'], 'target' => max(1, $reference['groupes']), 'color' => 'bg-navy', 'route' => route('crud.index', 'groupes')],
                            ['label' => 'Enseignants', 'icon' => 'fas fa-chalkboard-teacher', 'current' => $reference['teachers'], 'target' => max(1, $reference['teachers']), 'color' => 'bg-olive', 'route' => route('crud.index', 'professeurs')],
                        ];
                    @endphp
                    <div class="row">
                        @foreach ($progressItems as $item)
                        <div class="col-lg-6 col-md-12 mb-2">
                            <div class="info-box mb-1 shadow-sm" style="min-height:60px">
                                <a href="{{ $item['route'] }}" class="d-flex align-items-center text-dark text-decoration-none w-100">
                                    <span class="info-box-icon {{ $item['current'] > 0 ? str_replace('bg-', 'bg-', $item['color']) : 'bg-secondary' }}"><i class="{{ $item['icon'] }}"></i></span>
                                    <div class="info-box-content flex-grow-1">
                                        <span class="info-box-text">
                                            {{ $item['label'] }}
                                            <span class="badge {{ $item['current'] > 0 ? 'badge-success' : 'badge-secondary' }} float-right">{{ $item['current'] }}</span>
                                        </span>
                                        <div class="progress mt-1" style="height:14px">
                                            @php $pct = $item['target'] > 0 ? min(100, intdiv($item['current'] * 100, $item['target'])) : 0; @endphp
                                            <div class="progress-bar {{ $item['color'] }}" style="width: {{ $pct }}%"></div>
                                        </div>
                                        <span class="info-box-number small text-muted">{{ $pct }}% — {{ $item['current'] }} élément(s)</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title mb-0 text-white">
                        <i class="fas fa-calendar-week mr-2"></i>
                        Séances par jour
                    </h3>
                    <div class="card-tools">
                        <span class="badge badge-light"><i class="fas fa-bars mr-1"></i>{{ array_sum($charts['sessionsPerDay']['values']) }} séances</span>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="chartSessionsPerDay" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title mb-0 text-white">
                        <i class="fas fa-clock mr-2"></i>
                        Séances par créneau horaire
                    </h3>
                    <div class="card-tools">
                        <span class="badge badge-light"><i class="fas fa-history mr-1"></i>Charge horaire</span>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="chartSessionsPerSlot" height="120"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-lg-4">
            <div class="card card-success card-outline">
                <div class="card-header">
                    <h3 class="card-title mb-0 text-white">
                        <i class="fas fa-users mr-2"></i>
                        Groupes par semestre
                    </h3>
                </div>
                <div class="card-body">
                    <canvas id="chartGroupsBySemester" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card card-info card-outline">
                <div class="card-header">
                    <h3 class="card-title mb-0 text-white">
                        <i class="fas fa-book-open mr-2"></i>
                        Modules par filière
                    </h3>
                </div>
                <div class="card-body">
                    <canvas id="chartModulesByProgram" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card card-warning card-outline">
                <div class="card-header">
                    <h3 class="card-title mb-0 text-white">
                        <i class="fas fa-chart-area mr-2"></i>
                        Générations (10 derniers jours)
                    </h3>
                </div>
                <div class="card-body">
                    <canvas id="chartGenerationTrend" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Form for logout (hidden) -->
    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
        @csrf
    </form>

@endsection
@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Filtrage des semestres par filière dans le formulaire de génération.
    const programSelect = document.getElementById('program_id');
    const semesterSelect = document.getElementById('semester_id');
    const semesterOptions = Array.from(semesterSelect.querySelectorAll('option[data-program-id]'));
    function filterSemesters() {
        const programId = (programSelect && programSelect.value) || '';
        semesterOptions.forEach(function (option) {
            option.style.display = programId === '' || option.dataset.programId === programId ? '' : 'none';
        });
        if (semesterSelect.value && semesterSelect.selectedOptions[0] && semesterSelect.selectedOptions[0].style.display === 'none') {
            semesterSelect.selectedIndex = 0;
        }
    }
    if (programSelect) programSelect.addEventListener('change', filterSemesters);
    filterSemesters();

    const adminlteBlue = '#007bff';
    const chartFont = { family: "'Source Sans Pro', sans-serif", size: 13 };
    const tooltips = { mode: 'index', intersect: false, backgroundColor: 'rgba(0,0,0,0.8)', titleFont: chartFont, bodyFont: chartFont };

    if (typeof Chart !== 'undefined') {
        // Séances par jour
        new Chart(document.getElementById('chartSessionsPerDay'), {
            type: 'bar',
            data: {
                labels: @json($charts['sessionsPerDay']['labels']),
                datasets: [{
                    label: 'Séances',
                    data: @json($charts['sessionsPerDay']['values']),
                    backgroundColor: adminlteBlue,
                    borderColor: '#0056b3',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false }, tooltip: tooltips }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1, font: chartFont } }, x: { ticks: { font: chartFont } } } }
        });

        // Séances par créneau horaire
        new Chart(document.getElementById('chartSessionsPerSlot'), {
            type: 'line',
            data: {
                labels: @json($charts['sessionsPerSlot']['labels']),
                datasets: [{
                    label: 'Séances',
                    data: @json($charts['sessionsPerSlot']['values']),
                    fill: true,
                    backgroundColor: 'rgba(28, 200, 138, 0.15)',
                    borderColor: '#1cc88a',
                    tension: 0.35,
                    pointBackgroundColor: '#1cc88a'
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false }, tooltip: tooltips }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1, font: chartFont } }, x: { ticks: { font: chartFont } } } }
        });

        // Groupes par semestre
        new Chart(document.getElementById('chartGroupsBySemester'), {
            type: 'pie',
            data: {
                labels: @json($charts['groupsBySemester']['labels']),
                datasets: [{
                    data: @json($charts['groupsBySemester']['values']),
                    backgroundColor: ['#007bff', '#28a745', '#17a2b8', '#ffc107', '#dc3545', '#6f42c1'],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: chartFont } }, tooltip: tooltips } }
        });

        // Modules par filière
        new Chart(document.getElementById('chartModulesByProgram'), {
            type: 'doughnut',
            data: {
                labels: @json($charts['modulesByProgram']['labels']),
                datasets: [{
                    data: @json($charts['modulesByProgram']['values']),
                    backgroundColor: ['#fd7e14', '#20c997', '#6610f2', '#e83e8c', '#6c757d'],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: chartFont } }, tooltip: tooltips } }
        });

        // Trend générations
        new Chart(document.getElementById('chartGenerationTrend'), {
            type: 'line',
            data: {
                labels: @json($charts['generationTrend']['labels']),
                datasets: [{
                    label: 'Générations',
                    data: @json($charts['generationTrend']['values']),
                    fill: true,
                    backgroundColor: 'rgba(255, 193, 7, 0.2)',
                    borderColor: '#ffc107',
                    tension: 0.35,
                    pointBackgroundColor: '#ffc107'
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false }, tooltip: tooltips }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1, font: chartFont } }, x: { ticks: { font: chartFont } } } }
        });
    }
});

document.addEventListener('DOMContentLoaded', function () {
    if (typeof jQuery.fn.DataTable !== 'undefined' && jQuery('#generationsTable').length) {
        jQuery('#generationsTable').DataTable({
            language: {
                sProcessing: "Traitement en cours...",
                sSearch: "Rechercher :",
                sLengthMenu: "Afficher _MENU_ éléments",
                sInfo: "Affichage de l\'élément _START_ à _END_ sur _TOTAL_ éléments",
                sInfoEmpty: "Affichage de l\'élément 0 à 0 sur 0 élément",
                sInfoFiltered: "(filtré de _MAX_ éléments au total)",
                sLoadingRecords: "Chargement en cours...",
                sZeroRecords: "Aucun élément à afficher",
                sEmptyTable: "Aucune donnée disponible dans le tableau",
                paginate: { sFirst: "Premier", sPrevious: "Précédent", sNext: "Suivant", sLast: "Dernier" },
                aria: { sortAscending: ": activer pour trier la colonne par ordre croissant", sortDescending: ": activer pour trier la colonne par ordre décroissant" }
            },
            order: [[1, 'desc']],
            lengthMenu: [5, 10, 25],
            paging: false
        });
    }
});
</script>
@endpush
