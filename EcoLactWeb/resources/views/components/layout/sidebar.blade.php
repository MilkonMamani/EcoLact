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
<aside data-sidebar class="ecolact-sidebar fixed inset-y-0 left-0 z-50 flex flex-col text-white lg:sticky lg:top-0 lg:h-screen lg:translate-x-0" aria-label="Menú principal">
    <div class="ecolact-sidebar-scroll">
        <a class="ecolact-sidebar-brand" href="{{ route('home') }}" aria-label="EcoLact, inicio">
            <img src="{{ asset('images/ecolact-mark.svg') }}" width="48" height="61" alt="">
            <span><strong>EcoLact</strong><small>Del campo a tu mesa</small></span>
        </a>
        <nav class="ecolact-sidebar-nav" aria-label="Navegación por módulos">{{ $slot }}</nav>
        <div class="ecolact-sidebar-landscape" aria-hidden="true"></div>
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
