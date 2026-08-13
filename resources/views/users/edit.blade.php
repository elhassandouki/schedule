@extends('adminlte::page')

@section('title', 'Modifier un utilisateur')

@section('content_header')
    <h1><i class="fas fa-user-edit mr-2"></i>Modifier l'utilisateur : {{ $user->name }}</h1>
@stop

@section('content')
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card card-warning card-outline">
                <div class="card-header bg-warning">
                    <h3 class="card-title"><i class="fas fa-user-shield mr-2"></i>Rôle & Permissions</h3>
                </div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('users.update', $user) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="form-group">
                            <label for="name"><i class="fas fa-user mr-1"></i>Nom complet</label>
                            <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                            @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group">
                            <label for="email"><i class="fas fa-envelope mr-1"></i>Adresse e-mail</label>
                            <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                            @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group">
                            <label for="role"><i class="fas fa-id-badge mr-1"></i>Rôle</label>
                            <select id="role" name="role" class="form-control @error('role') is-invalid @enderror" required>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->name }}" @selected(old('role', $user->role) === $role->name)>
                                        {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('role')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <small class="form-text text-muted">
                                Le changement de rôle met à jour automatiquement les permissions de cet utilisateur.
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="password"><i class="fas fa-lock mr-1"></i>Nouveau mot de passe <span class="text-muted">(optionnel)</span></label>
                            <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="Laisser vide pour ne pas changer">
                            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group">
                            <label for="password_confirmation"><i class="fas fa-lock mr-1"></i>Confirmer le mot de passe</label>
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="Confirmer le nouveau mot de passe">
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('users.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i>Retour</a>
                            <button type="submit" class="btn btn-warning"><i class="fas fa-save mr-1"></i>Enregistrer</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card card-info">
                <div class="card-header bg-info">
                    <h3 class="card-title"><i class="fas fa-key mr-2"></i>Permissions actuelles</h3>
                </div>
                <div class="card-body">
                    @if ($user->getPermissionNames()->isNotEmpty())
                        <div>
                            @foreach ($user->getPermissionNames()->sort() as $permission)
                                <span class="badge badge-info m-1">{{ $permission }}</span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted">Aucune permission assignée.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@stop
