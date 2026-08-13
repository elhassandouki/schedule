@extends('adminlte::page')

@section('title', 'Permissions : ' . ucfirst(str_replace('_', ' ', $role->name)))

@section('content_header')
    <h1>
        <i class="fas fa-key"></i> Permissions du rôle :
        <span class="badge {{
            $role->name === 'sous_admin' ? 'badge-warning' :
            ($role->name === 'chef_departement' ? 'badge-info' :
            ($role->name === 'chef_filiere' ? 'badge-primary' : 'badge-secondary'))
        }}">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</span>
    </h1>
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

    <div class="row">
        <div class="col-md-8">
            <div class="card card-warning">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-sliders-h"></i> Choisir les permissions</h3>
                    <span class="card-tools">
                        <button type="button" class="btn btn-tool btn-xs btn-success" id="btnAll">
                            <i class="fas fa-check-double"></i> Tout sélectionner
                        </button>
                        <button type="button" class="btn btn-tool btn-xs btn-danger" id="btnNone">
                            <i class="fas fa-eraser"></i> Tout désélectionner
                        </button>
                    </span>
                </div>
                <form action="{{ route('roles.update', $role) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="card-body">
                        <p class="text-muted text-sm mb-3">
                            Cochez les permissions que le rôle <strong>« {{ str_replace('_', ' ', $role->name) }} »</strong>
                            doit posséder. Les permissions sont regroupées par ressource.
                        </p>

                        <div class="row">
                            @foreach($byResource as $resource => $group)
                                <div class="col-sm-6 col-md-4 mb-3">
                                    <div class="card card-outline card-secondary h-100">
                                        <div class="card-header p-2 bg-gray">
                                            <strong class="text-capitalize">
                                                <i class="fas fa-cube"></i> {{ str_replace('_', ' ', $group['label']) }}
                                            </strong>
                                        </div>
                                        <div class="card-body p-2">
                                            @foreach($group['actions'] as $action)
                                                <div class="icheck-primary">
                                                    <input type="checkbox"
                                                           name="permissions[]"
                                                           id="perm_{{ $loop->parent->index }}_{{ $loop->index }}"
                                                           value="{{ $action['name'] }}"
                                                           class="perm-checkbox"
                                                           {{ $action['granted'] ? 'checked' : '' }}>
                                                    <label for="perm_{{ $loop->parent->index }}_{{ $loop->index }}">
                                                        {{ ucfirst($action['verb']) }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="card-footer">
                        <a href="{{ route('roles.index') }}" class="btn btn-default">
                            <i class="fas fa-arrow-left"></i> Retour
                        </a>
                        <button type="submit" class="btn btn-warning float-right">
                            <i class="fas fa-save"></i> Enregistrer les permissions
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card card-info">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-info-circle"></i> Récapitulatif</h3>
                </div>
                <div class="card-body">
                    <p class="text-sm mb-1">
                        Permissions sélectionnées :
                        <span id="selectedCount" class="badge badge-info">
                            {{ collect($byResource)->sum(fn ($g) => collect($g['actions'])->where('granted', true)->count()) }}
                        </span>
                        sur {{ $permissions->count() }}
                    </p>
                    <hr>
                    <ul class="list-unstyled text-sm" id="selectedList">
                        @foreach($permissions as $permission)
                            @if($role->hasPermissionTo($permission))
                                <li><i class="fas fa-check text-success"></i> {{ $permission->name }}</li>
                            @endif
                        @endforeach
                    </ul>
                    <p class="text-xs text-muted mt-2">
                        <i class="fas fa-lock"></i> Le rôle super admin possède automatiquement toutes les permissions
                        et ne peut pas être modifié.
                    </p>
                </div>
            </div>
        </div>
    </div>

@stop

@section('css')
    <style>
        .icheck-primary { margin-bottom: .25rem; }
        .icheck-primary label { font-weight: normal; }
    </style>
@stop

@section('js')
<script>
$(function () {
    function updateSummary() {
        var count = $('.perm-checkbox:checked').length;
        var total = $('.perm-checkbox').length;
        $('#selectedCount').text(count);
        $('#selectedCount')
            .removeClass('badge-info badge-warning badge-danger')
            .addClass(count === total ? 'badge-success' : (count === 0 ? 'badge-danger' : 'badge-info'));
        var list = $('#selectedList');
        list.empty();
        if (count === 0) {
            list.append('<li class="text-muted"><i class="fas fa-times text-danger"></i> Aucune permission sélectionnée</li>');
        } else {
            $('.perm-checkbox:checked').each(function () {
                list.append('<li><i class="fas fa-check text-success"></i> ' + $(this).val() + '</li>');
            });
        }
    }
    $('.perm-checkbox').on('change', updateSummary);
    $('#btnAll').on('click', function () {
        $('.perm-checkbox').prop('checked', true);
        updateSummary();
    });
    $('#btnNone').on('click', function () {
        $('.perm-checkbox').prop('checked', false);
        updateSummary();
    });
});
</script>
@stop
