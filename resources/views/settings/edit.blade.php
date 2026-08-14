@extends('adminlte::page')

@section('title', 'Paramètres de l\'établissement')

@section('content_header')
    <h1><i class="fas fa-university"></i> Paramètres de l'établissement</h1>
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
        {{-- Informations générales --}}
        <div class="col-md-8">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-info-circle"></i> Informations générales</h3>
                </div>
                <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="card-body">
                        <div class="form-group">
                            <label for="establishment_name"><i class="fas fa-university"></i> Nom de l'établissement <span class="text-danger">*</span></label>
                            <input type="text" name="establishment_name" id="establishment_name"
                                   class="form-control @error('establishment_name') is-invalid @enderror"
                                   value="{{ old('establishment_name', $settings['establishment_name']) }}"
                                   placeholder="Ex : Université Djilali Liabès de Sidi Bel Abbès">
                            @error('establishment_name')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="establishment_address"><i class="fas fa-map-marker-alt"></i> Adresse</label>
                            <input type="text" name="establishment_address" id="establishment_address"
                                   class="form-control @error('establishment_address') is-invalid @enderror"
                                   value="{{ old('establishment_address', $settings['establishment_address']) }}"
                                   placeholder="Ex : Route de Tlemcen, Sidi Bel Abbès">
                            @error('establishment_address')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="establishment_phone"><i class="fas fa-phone"></i> Téléphone</label>
                            <input type="text" name="establishment_phone" id="establishment_phone"
                                   class="form-control @error('establishment_phone') is-invalid @enderror"
                                   value="{{ old('establishment_phone', $settings['establishment_phone']) }}"
                                   placeholder="Ex : +213 48 54 00 00">
                            @error('establishment_phone')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="establishment_email"><i class="fas fa-envelope"></i> E-mail</label>
                            <input type="email" name="establishment_email" id="establishment_email"
                                   class="form-control @error('establishment_email') is-invalid @enderror"
                                   value="{{ old('establishment_email', $settings['establishment_email']) }}"
                                   placeholder="Ex : contact@universite.dz">
                            @error('establishment_email')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary float-right">
                            <i class="fas fa-save"></i> Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Logo --}}
        <div class="col-md-4">
            <div class="card card-success card-outline">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-image"></i> Logo de l'établissement</h3>
                </div>
                <form action="{{ route('settings.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="card-body text-center">
                        <div class="d-flex justify-content-center mb-3">
                            <img id="logoPreview"
                                 src="{{ $settings['logo_path'] ? asset('storage/' . $settings['logo_path']) : asset('vendor/adminlte/dist/img/AdminLTELogo.png') }}"
                                 alt="Logo"
                                 class="img-circle elevation-3"
                                 style="max-width: 150px; max-height: 150px; object-fit: contain; background:#fff; padding:10px">
                        </div>

                        <div class="custom-file text-left">
                            <input type="file" name="logo" id="logoInput" class="custom-file-input" accept="image/jpeg,image/png,image/jpg,image/webp,image/gif">
                            <label class="custom-file-label" for="logoInput" data-browse="Parcourir">
                                {{ $settings['logo_path'] ? 'Changer le logo' : 'Choisir un logo' }}
                            </label>
                            @error('logo')
                                <span class="text-danger text-sm">{{ $message }}</span>
                            @enderror
                            <p class="text-muted text-xs mt-2">JPEG, PNG, WEBP ou GIF — maximum 2 Mo.</p>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-success btn-block">
                            <i class="fas fa-upload"></i> Enregistrer le logo
                        </button>
                    </div>
                </form>

                @if($settings['logo_path'])
                    <div class="card-footer border-top-0 pt-0">
                        <form action="{{ route('settings.logo.remove') }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-block btn-sm"
                                    onclick="return confirm('Supprimer le logo actuel ?');">
                                <i class="fas fa-trash"></i> Supprimer le logo
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Prévisualisation en temps réel --}}
    <div class="card card-outline card-info">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-eye"></i> Aperçu</h3>
        </div>
        <div class="card-body text-center bg-light">
            <img id="previewLogo" src="" alt="" style="display:none; max-height:60px; margin-right:10px; vertical-align:middle;">
            <strong id="previewName" class="text-lg" style="vertical-align:middle">{{ $settings['establishment_name'] }}</strong>
            <p class="text-muted text-sm mb-0 mt-2">
                <span id="previewAddress">{{ $settings['establishment_address'] }}</span>
                <span id="previewContact" class="{{ $settings['establishment_address'] ? 'ml-2' : '' }}">
                    @if($settings['establishment_phone'])<i class="fas fa-phone text-xs"></i> {{ $settings['establishment_phone'] }}@endif
                    @if($settings['establishment_email'])<span class="ml-2"><i class="fas fa-envelope text-xs"></i> {{ $settings['establishment_email'] }}</span>@endif
                </span>
            </p>
        </div>
    </div>

@stop

@section('js')
<script>
$(function () {
    // Prévisualisation du logo avant envoi
    $('#logoInput').on('change', function (e) {
        var file = e.target.files[0];
        if (file) {
            var reader = new FileReader();
            reader.onload = function (ev) {
                $('#logoPreview').attr('src', ev.target.result);
            };
            reader.readAsDataURL(file);
        }
    });

    // Mise à jour de l'aperçu à la saisie
    function syncPreview() {
        var name = $('#establishment_name').val() || 'Nom de l\'établissement';
        $('#previewName').text(name);
        $('#previewAddress').text($('#establishment_address').val());
    }
    $('#establishment_name, #establishment_address').on('input', syncPreview);

    // Synchronisation automatique : le logo choisi s'envoie avec le formulaire principal
    // via l'attribut form sur le champ fichier (pas nécessaire ici : formulaire dédié).
});
</script>
@stop
