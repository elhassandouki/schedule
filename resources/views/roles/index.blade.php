@extends('adminlte::page')

@section('title', 'Rôles & Permissions')

@section('content_header')
    <h1><i class="fas fa-user-shield"></i> Rôles & Permissions</h1>
@stop

@section('content')

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Fermer">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-triangle"></i> {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Fermer">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    {{-- Statistiques --}}
    <div class="row">
        <div class="col-sm-6 col-md-3">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ $rolesCount }}</h3>
                    <p>Rôles</p>
                </div>
                <div class="icon"><i class="fas fa-users-cog"></i></div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $permissionsCount }}</h3>
                    <p>Permissions</p>
                </div>
                <div class="icon"><i class="fas fa-key"></i></div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h3>{{ $assignmentsCount }}</h3>
                    <p>Assignations rôle &rarr; permission</p>
                </div>
                <div class="icon"><i class="fas fa-link"></i></div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="small-box bg-danger">
                <div class="inner">
                    <h3>{{ count($permissions) ? $permissionsCount : 0 }}</h3>
                    <p>Actions par ressource</p>
                </div>
                <div class="icon"><i class="fas fa-layer-group"></i></div>
            </div>
        </div>
    </div>

    {{-- Tableau des rôles --}}
    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-user-shield"></i> Liste des rôles</h3>
            <span class="card-tools">
                <span class="badge badge-info">Gestion par spatie/laravel-permission</span>
            </span>
        </div>
        <div class="card-body table-responsive p-0">
            <table id="rolesTable" class="table table-bordered table-striped text-sm">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Rôle</th>
                        <th>Permissions actives</th>
                        <th>Détail</th>
                        <th style="width:140px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($roles as $role)
                        <tr>
                            <td>{{ $role->id }}</td>
                            <td>
                                <span class="badge {{
                                    $role->name === 'super_admin' ? 'badge-danger' :
                                    ($role->name === 'sous_admin' ? 'badge-warning' :
                                    ($role->name === 'chef_departement' ? 'badge-info' :
                                    ($role->name === 'chef_filiere' ? 'badge-primary' : 'badge-secondary')))
                                }}">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</span>
                            </td>
                            <td>
                                @php $count = $role->permissions->count(); @endphp
                                @if($role->name === 'super_admin')
                                    <span class="badge badge-success">Toutes ({{ $permissionsCount }})</span>
                                @else
                                    <span class="badge badge-secondary">{{ $count }} / {{ $permissionsCount }}</span>
                                @endif
                            </td>
                            <td>
                                @if($role->name === 'super_admin')
                                    <em class="text-muted">Accès complet à toutes les fonctionnalités.</em>
                                @else
                                    @foreach($role->permissions->sortBy('name')->take(6) as $perm)
                                        <span class="badge badge-outline-info">{{ $perm->name }}</span>
                                    @endforeach
                                    @if($role->permissions->count() > 6)
                                        <span class="badge badge-dark">+{{ $role->permissions->count() - 6 }} autres</span>
                                    @endif
                                    @if($role->permissions->isEmpty())
                                        <em class="text-muted">Aucune permission assignée.</em>
                                    @endif
                                @endif
                            </td>
                            <td>
                                @if($role->name === 'super_admin')
                                    <span class="text-muted text-xs">
                                        <i class="fas fa-lock"></i> Non modifiable
                                    </span>
                                @else
                                    <a href="{{ route('roles.edit', $role) }}" class="btn btn-xs btn-warning" title="Modifier les permissions">
                                        <i class="fas fa-key"></i> Permissions
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

@stop

@section('css')
    <style>
        .badge-outline-info { border:1px solid #17a2b8; color:#17a2b8; }
    </style>
@stop

@section('plugins.Datatables', true)
@section('js')
<script>
$(function () {
    $('#rolesTable').DataTable({
        order: [[0, 'asc']],
        pageLength: 10,
        language: {
            search: "Rechercher :",
            lengthMenu: "Afficher _MENU_ entrées",
            info: "Affichage de _START_ à _END_ sur _TOTAL_ rôles",
            infoEmpty: "Aucun rôle disponible",
            infoFiltered: "(filtré sur _MAX_ rôles)",
            zeroRecords: "Aucun rôle correspondant trouvé",
            paginate: { first: "Premier", last: "Dernier", next: "Suivant", previous: "Précédent" }
        }
    });
});
</script>
@stop
