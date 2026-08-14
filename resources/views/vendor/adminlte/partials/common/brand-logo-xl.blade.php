@inject('layoutHelper', 'JeroenNoten\LaravelAdminLte\Helpers\LayoutHelper')

@php
    $dashboard_url = View::getSection('dashboard_url') ?? config('adminlte.dashboard_url', 'home');
    $estab = \App\Models\Setting::allValues();
    $brand_logo = $estab['logo_path'] ? asset('storage/' . $estab['logo_path']) : asset(config('adminlte.logo_img', 'vendor/adminlte/dist/img/AdminLTELogo.png'));
    $brand_text = $estab['establishment_name'] ?? config('adminlte.title', 'PlanifUni');
@endphp

@if (config('adminlte.use_route_url', false))
    @php( $dashboard_url = $dashboard_url ? route($dashboard_url) : '' )
@else
    @php( $dashboard_url = $dashboard_url ? url($dashboard_url) : '' )
@endif

<a href="{{ $dashboard_url }}"
    @if($layoutHelper->isLayoutTopnavEnabled())
        class="navbar-brand logo-switch {{ config('adminlte.classes_brand') }}"
    @else
        class="brand-link logo-switch {{ config('adminlte.classes_brand') }}"
    @endif>

    {{-- Small brand logo (logo dynamique de l'établissement) --}}
    <img src="{{ $brand_logo }}"
         alt="{{ $brand_text }}"
         class="{{ config('adminlte.logo_img_class', 'brand-image-xl') }} logo-xs"
         style="background:#fff; border-radius:50%; padding:2px">

    {{-- Large brand logo (texte dynamique de l'établissement) --}}
    <span class="brand-text-xs" style="{{ $estab['logo_path'] ? 'max-width:120px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:inline-block; vertical-align:middle' : '' }}">
        {{ $brand_text }}
    </span>

</a>
