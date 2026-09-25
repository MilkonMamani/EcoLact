@php
    $user = auth()->user();
    $perfil = match (true) {
        $user?->esProveedor() => $user->proveedor,
        $user?->esAcopiador() => $user->acopiador,
        $user?->esCalidad() => $user->analistaCalidad,
        $user?->esEncargadoPlanta() => $user->encargadoPlanta,
        $user?->esVentas() => $user->personalVentas,
        default => null,
    };
    $nombre = $perfil ? trim($perfil->nombres.' '.$perfil->apellidos) : ($user?->username ?? 'Usuario');
    $rol = match (true) {
        $user?->esAdministrador() => 'Administrador',
        $user?->esAcopiador() => 'Acopiador',
        $user?->esProveedor() => 'Proveedor',
        $user?->esCalidad() => 'Calidad',
        $user?->esEncargadoPlanta() => 'Encargado de planta',
        $user?->esVentas() => 'Ventas',
        default => '',
    };
@endphp
<button type="button" data-sidebar-toggle data-sidebar-fallback class="ecolact-sidebar-fallback" aria-label="Abrir menú" aria-controls="ecolact-sidebar" aria-expanded="false" hidden>
    <svg class="ecolact-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
</button>
<aside id="ecolact-sidebar" data-sidebar class="ecolact-sidebar fixed inset-y-0 left-0 z-50 flex flex-col text-white lg:sticky lg:top-0 lg:h-screen lg:translate-x-0" aria-label="Menú principal">
    <div class="ecolact-sidebar-header">
        <a class="ecolact-sidebar-brand" href="{{ route('home') }}" aria-label="EcoLact, inicio">
            <img src="{{ asset('images/vaquita.png') }}" width="42" height="42" alt="">
            <span><strong>EcoLact</strong><small>Gestión integral</small></span>
        </a>
        <button type="button" data-sidebar-close class="ecolact-sidebar-close" aria-label="Cerrar menú">
            <svg class="ecolact-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6" /></svg>
        </button>
    </div>
    <div class="ecolact-sidebar-scroll">
        <nav class="ecolact-sidebar-nav" aria-label="Navegación por módulos">
            <p class="ecolact-sidebar-heading">Dashboard</p>
            {{ $slot }}
        </nav>
    </div>
    <details class="ecolact-sidebar-account">
        <summary>
            <span class="ecolact-sidebar-avatar">{{ mb_strtoupper(mb_substr($nombre, 0, 1)) }}</span>
            <span class="ecolact-sidebar-identity"><strong>{{ $nombre }}</strong><small>{{ $rol }}</small></span>
            <x-layout.sidebar-icon name="chevron" />
        </summary>
        <div class="ecolact-sidebar-menu">
            @if ($user?->esAdministrador())
                <a href="{{ route('admin.administrador.show') }}"><x-layout.sidebar-icon name="users" /> Mi perfil</a>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"><x-layout.sidebar-icon name="logout" /> Cerrar sesión</button>
            </form>
        </div>
    </details>
</aside>
