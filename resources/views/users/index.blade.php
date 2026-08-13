@extends('adminlte::page')

@section('title', 'Utilisateurs & Rôles')

@section('content_header')
    <h1><i class="fas fa-users-cog mr-2"></i>Utilisateurs & Rôles</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card card-primary card-outline">
                <div class="card-header bg-primary">
                    <h3 class="card-title"><i class="fas fa-user-shield mr-2"></i>Gestion des comptes et des rôles</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Les rôles et permissions sont gérés par <strong>spatie/laravel-permission</strong>.
                        Modifiez le rôle d'un utilisateur pour changer automatiquement ses permissions
                        (super_admin, sous_admin, chef_departement, chef_filiere, prof).
                    </p>

                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible">
                            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                            {{ session('error') }}
                        </div>
                    @endif

                    <table id="usersTable" class="table table-bordered table-striped table-hover w-100">
                        <thead class="bg-light">
                            <tr>
                                <th>ID</th>
                                <th>Nom</th>
                                <th>E-mail</th>
                                <th>Rôle</th>
                                <th>Créé le</th>
                                <th style="width: 180px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                @php
                                    $roleBadge = match ($user->role) {
                                        'super_admin' => ['bg', 'danger', 'fas fa-crown'],
                                        'sous_admin' => ['bg', 'warning', 'fas fa-user-tie'],
                                        'chef_departement' => ['bg', 'info', 'fas fa-building'],
                                        'chef_filiere' => ['bg', 'success', 'fas fa-graduation-cap'],
                                        default => ['bg', 'secondary', 'fas fa-chalkboard-teacher'],
                                    };
                                @endphp
                                <tr>
                                    <td>{{ $user->id }}</td>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td><span class="badge {{ $roleBadge[0] }}-{{ $roleBadge[1] }}">{{ ucfirst(str_replace('_', ' ', $user->role)) }} <i class="{{ $roleBadge[2] }}"></i></span></td>
                                    <td>{{ $user->created_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-warning" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        @if ($user->id !== auth()->id())
                                            <form action="{{ route('users.destroy', $user) }}" method="POST" class="d-inline" onsubmit="return confirm('Supprimer cet utilisateur ?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop

@section('plugins.DataTables', true)
@section('plugins.DataTablesPlugins', true)

@push('js')
<script>
    $(function () {
        $('#usersTable').DataTable({
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
            },
            pageLength: 15,
            order: [[0, 'desc']],
            columnDefs: [
                { targets: -1, orderable: false }
            ]
        });
    });
</script>
@endpush
