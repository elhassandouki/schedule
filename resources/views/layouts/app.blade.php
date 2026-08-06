@extends('adminlte::page')

@section('title', config('app.name', 'Planif Uni'))

@section('body_class', 'sidebar-mini')

@push('css')
<style>
    :root {
        --primary: #007bff;
        --success: #28a745;
        --info: #17a2b8;
        --warning: #ffc107;
        --danger: #dc3545;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .nav-link.active {
        background-color: rgba(255, 255, 255, 0.1) !important;
        border-left: 4px solid #007bff;
    }

    .card {
        border-top: 3px solid #007bff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .card.bg-light {
        background-color: #f8f9fa !important;
    }

    .btn-group-sm > .btn,
    .btn-sm {
        padding: 0.35rem 0.65rem;
        font-size: 0.85rem;
    }

    .table-responsive {
        border-radius: 4px;
    }

    .form-control:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }

    .alert {
        border-radius: 4px;
        border: none;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .badge {
        padding: 0.5rem 0.75rem;
        font-weight: 500;
    }

    .small-box {
        border-radius: 4px;
        transition: all 0.3s ease;
    }

    .small-box:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }

    .breadcrumb {
        background-color: #f8f9fa;
        border-radius: 4px;
        padding: 0.75rem 1rem;
    }

    .page-header {
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e9ecef;
    }

    .filter-section {
        background-color: #f8f9fa;
        padding: 1rem;
        border-radius: 4px;
        margin-bottom: 1.5rem;
    }

    .text-truncate-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>
@endpush

@section('content')
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">@yield('page_title', 'Page')</h1>
                </div>
                <div class="col-sm-6">
                    @yield('breadcrumb')
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Erreur!</strong> Veuillez corriger les erreurs ci-dessous:
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Succès!</strong> {{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            @if (session('warning'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong>Attention!</strong> {{ session('warning') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            @yield('content')
        </div>
    </div>
@endsection

@section('adminlte_css')
    @stack('css')
@endsection

@section('adminlte_js')
    @stack('js')

    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').not('.alert-permanent').fadeOut('slow');
        }, 5000);

        // Confirm delete
        document.querySelectorAll('[data-confirm]').forEach(function(el) {
            el.addEventListener('click', function(e) {
                if (!confirm(this.dataset.confirm)) {
                    e.preventDefault();
                }
            });
        });

        // Tooltip
        $('[data-toggle="tooltip"]').tooltip();

        // Popover
        $('[data-toggle="popover"]').popover();
    </script>
@endsection
