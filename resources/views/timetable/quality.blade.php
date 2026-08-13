@extends('layouts.app')

@section('content')
<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <h2>{{ $program->name }} → {{ $semester->name }} — Timetable Quality Report</h2>
        </div>
    </div>

    <!-- Quality Score Card -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-left-{{ $quality['quality_score'] >= 90 ? 'success' : ($quality['quality_score'] >= 75 ? 'info' : ($quality['quality_score'] >= 60 ? 'warning' : 'danger')) }}">
                <div class="card-header bg-primary">
                    <h4 class="mb-0">Quality Score</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="text-center">
                                <div class="display-4 {{ $quality['quality_score'] >= 90 ? 'text-success' : ($quality['quality_score'] >= 75 ? 'text-info' : ($quality['quality_score'] >= 60 ? 'text-warning' : 'text-danger')) }}">
                                    {{ $quality['quality_score'] }}/100
                                </div>
                                <p class="lead">{{ $quality['quality_rating'] }}</p>
                                <p class="text-muted">
                                    @if ($quality['quality_score'] >= 90)
                                        Excellent timetable with minimal issues
                                    @elseif ($quality['quality_score'] >= 75)
                                        Good timetable with minor improvements possible
                                    @elseif ($quality['quality_score'] >= 60)
                                        Acceptable but needs improvements
                                    @else
                                        Poor timetable, significant issues detected
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <strong>Coverage:</strong>
                                <div class="progress">
                                    <div class="progress-bar {{ $quality['coverage_percentage'] >= 90 ? 'bg-success' : 'bg-warning' }}"
                                         style="width: {{ $quality['coverage_percentage'] }}%">
                                        {{ $quality['coverage_percentage'] }}%
                                    </div>
                                </div>
                                <small class="text-muted">
                                    {{ $quality['generated_sessions'] }} of {{ $quality['required_sessions'] }} sessions scheduled
                                </small>
                            </div>
                            <div class="mb-3">
                                <strong>Metrics:</strong>
                                <ul class="list-unstyled">
                                    <li><span class="badge badge-{{ $quality['conflict_count'] === 0 ? 'success' : 'danger' }}">
                                        {{ $quality['conflict_count'] }} Hard Conflicts
                                    </span></li>
                                    <li><span class="badge badge-{{ $quality['warning_count'] === 0 ? 'success' : 'warning' }}">
                                        {{ $quality['warning_count'] }} Warnings
                                    </span></li>
                                    <li><span class="badge badge-danger">
                                        {{ $quality['skipped_sessions'] }} Skipped Sessions
                                    </span></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hard Conflicts -->
    @if ($quality['conflict_count'] > 0)
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-left-danger">
                <div class="card-header bg-primary">
                    <h5 class="mb-0 text-danger">
                        <i class="fas fa-exclamation-circle"></i> Hard Conflicts ({{ $quality['conflict_count'] }})
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        @foreach ($quality['hard_conflicts'] as $conflict)
                        <li class="list-group-item border-left-danger">
                            <strong>{{ $conflict['type'] }}:</strong> {{ $conflict['message'] }}
                            @if (isset($conflict['day']))
                                <br><small class="text-muted">{{ $conflict['day'] }} {{ $conflict['timeslot'] }}</small>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <strong>No hard conflicts detected.</strong>
            </div>
        </div>
    </div>
    @endif

    <!-- Skipped Sessions -->
    @if ($quality['skipped_sessions'] > 0)
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-left-warning">
                <div class="card-header bg-primary">
                    <h5 class="mb-0 text-warning">
                        <i class="fas fa-exclamation-triangle"></i> Skipped Sessions ({{ $quality['skipped_sessions'] }})
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">These sessions could not be scheduled due to unavailable slots.</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Groupe</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse (DB::table('timetable_generation_skips')->where('semester_id', $semester->id)->get() as $skip)
                                <tr>
                                    <td>{{ $skip->subject_name }}</td>
                                    <td>{{ $skip->group_name ?? $skip->section_name }}</td>
                                    <td><small>{{ $skip->reason }}</small></td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No detailed skip records found</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Soft Warnings -->
    @if ($quality['warning_count'] > 0)
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-left-warning">
                <div class="card-header bg-primary">
                    <h5 class="mb-0 text-warning">
                        <i class="fas fa-exclamation"></i> Warnings ({{ $quality['warning_count'] }})
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        @foreach ($quality['soft_warnings'] as $warning)
                        <li class="list-group-item border-left-warning">
                            <strong>{{ ucfirst(str_replace('_', ' ', $warning['type'])) }}:</strong> 
                            {{ $warning['message'] }}
                            @if (isset($warning['value']))
                                <span class="badge badge-warning ml-2">{{ $warning['value'] }}</span>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- Teacher Workload -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary">
                    <h5 class="mb-0">Teacher Workload</h5>
                </div>
                <div class="card-body">
                    @if ($quality['workload']['teachers'])
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Teacher</th>
                                    <th>Sessions</th>
                                    <th>Hours/Week</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($quality['workload']['teachers'] as $load)
                                <tr class="{{ $load['warning'] ? 'table-warning' : '' }}">
                                    <td>{{ $load['teacher_name'] }}</td>
                                    <td><span class="badge badge-info">{{ $load['sessions_count'] }}</span></td>
                                    <td><strong>{{ $load['hours_per_week'] }}h</strong></td>
                                    <td>
                                        @if ($load['warning'])
                                            <span class="badge badge-warning">{{ $load['warning'] }}</span>
                                        @else
                                            <span class="badge badge-success">✓ Balanced</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-muted">No teacher workload data available.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Classroom Utilization -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary">
                    <h5 class="mb-0">Classroom Utilization</h5>
                </div>
                <div class="card-body">
                    @if ($quality['classroom_utilization'])
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Classroom</th>
                                    <th>Usage</th>
                                    <th>Utilization</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($quality['classroom_utilization'] as $util)
                                <tr class="{{ $util['warning'] ? 'table-warning' : '' }}">
                                    <td>{{ $util['classroom_name'] }}</td>
                                    <td>{{ $util['usage_count'] }}/{{ $util['total_slots'] }} slots</td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar {{ $util['utilization_percent'] >= 50 ? 'bg-success' : 'bg-warning' }}"
                                                 style="width: {{ $util['utilization_percent'] }}%">
                                                {{ $util['utilization_percent'] }}%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($util['warning'])
                                            <span class="badge badge-warning">{{ $util['warning'] }}</span>
                                        @else
                                            <span class="badge badge-success">✓ Good</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-muted">No classroom utilization data available.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Consecutive Sessions -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary">
                    <h5 class="mb-0">Consecutive Sessions (by Teacher & Day)</h5>
                </div>
                <div class="card-body">
                    @if ($quality['consecutive'])
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Teacher</th>
                                    <th>Day</th>
                                    <th>Max Consecutive</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($quality['consecutive'] as $consec)
                                <tr class="{{ $consec['warning'] ? 'table-warning' : '' }}">
                                    <td>{{ $consec['teacher_name'] }}</td>
                                    <td>{{ $consec['day_name'] }}</td>
                                    <td><span class="badge badge-info">{{ $consec['max_consecutive'] }}</span></td>
                                    <td>
                                        @if ($consec['warning'])
                                            <span class="badge badge-warning">{{ $consec['warning'] }}</span>
                                        @else
                                            <span class="badge badge-success">✓</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-muted">No consecutive session data available.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Gaps -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary">
                    <h5 class="mb-0">Schedule Gaps (by Group & Day)</h5>
                </div>
                <div class="card-body">
                    @if ($quality['gaps'])
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Groupe</th>
                                    <th>Day</th>
                                    <th>Gap</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($quality['gaps'] as $gap)
                                <tr class="{{ $gap['has_gap'] ? 'table-warning' : '' }}">
                                    <td>{{ $gap['group_name'] ?? ($gap['section_name'] ?? '') }}</td>
                                    <td>{{ $gap['day_name'] }}</td>
                                    <td>
                                        @if ($gap['has_gap'])
                                            <span class="badge badge-warning">Has gap</span>
                                        @else
                                            <span class="badge badge-success">✓ Continuous</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-muted">No gap data available.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Back Button -->
    <div class="row mb-4">
        <div class="col-md-12">
            <a href="{{ route('dashboard') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>
@endsection
