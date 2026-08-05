<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Planif Uni — Connexion</title>

    <link rel="stylesheet" href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/dist/css/adminlte.min.css') }}">

    <style>
        .login-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .login-box {
            width: 100%;
            max-width: 360px;
            margin: 7% auto;
        }

        .login-logo {
            font-size: 35px;
            font-weight: bold;
            margin-bottom: 2rem;
            color: white;
            text-align: center;
        }

        .login-logo b {
            color: #fff;
        }

        .login-card-body {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .form-control {
            border-radius: 5px;
            border: 1px solid #ddd;
            padding: 10px 15px;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-primary {
            background-color: #667eea;
            border-color: #667eea;
            border-radius: 5px;
            padding: 10px 20px;
        }

        .btn-primary:hover {
            background-color: #764ba2;
            border-color: #764ba2;
        }

        .text-danger {
            color: #dc3545 !important;
            font-size: 0.875rem;
        }

        .login-box-msg {
            color: #667eea;
            font-weight: 600;
        }
    </style>
</head>

<body class="hold-transition login-page">
    <div class="login-box">
        <div class="login-logo">
            <i class="fas fa-graduation-cap" style="margin-right: 10px;"></i>
            <b>Planif</b>Uni
        </div>

        <div class="card">
            <div class="card-body login-card-body">
                <p class="login-box-msg">
                    <i class="fas fa-lock mr-2"></i>
                    Gestion des emplois du temps
                </p>

                <form method="post">
                    @csrf

                    <div class="input-group mb-3">
                        <input 
                            type="email" 
                            name="email" 
                            class="form-control @error('email') is-invalid @enderror" 
                            placeholder="Adresse e-mail" 
                            value="{{ old('email') }}"
                            required
                            autofocus
                        />
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-envelope"></span>
                            </div>
                        </div>
                        @error('email')
                            <div class="invalid-feedback d-block">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="input-group mb-3">
                        <input 
                            type="password" 
                            name="password" 
                            class="form-control @error('password') is-invalid @enderror" 
                            placeholder="Mot de passe" 
                            required
                        />
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-lock"></span>
                            </div>
                        </div>
                        @error('password')
                            <div class="invalid-feedback d-block">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-sign-in-alt mr-2"></i>
                                Se connecter
                            </button>
                        </div>
                    </div>
                </form>

                <hr class="my-3">

                <p class="text-center text-muted small mb-0">
                    <i class="fas fa-info-circle mr-2"></i>
                    Identifiants de test disponibles
                </p>
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/adminlte/dist/js/adminlte.min.js') }}"></script>
</body>

</html>
