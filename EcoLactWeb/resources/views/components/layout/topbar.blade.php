@props(['title' => 'Panel'])

@php
    $user = auth()->user();
    $nombreMostrado = match (true) {
        $user?->esProveedor() && $user->proveedor => trim($user->proveedor->nombres . ' ' . $user->proveedor->apellidos),
        $user?->esAcopiador() && $user->acopiador => trim($user->acopiador->nombres . ' ' . $user->acopiador->apellidos),
        $user?->esCalidad() && $user->analistaCalidad => trim($user->analistaCalidad->nombres . ' ' . $user->analistaCalidad->apellidos),
        $user?->esEncargadoPlanta() && $user->encargadoPlanta => trim($user->encargadoPlanta->nombres . ' ' . $user->encargadoPlanta->apellidos),
        $user?->esVentas() && $user->personalVentas => trim($user->personalVentas->nombres . ' ' . $user->personalVentas->apellidos),
        default => $user?->username ?? 'Usuario',
    };
    $rolMostrado = match (true) {
        $user?->esAdministrador() => 'Administrador',
        $user?->esAcopiador() => 'Acopiador',
        $user?->esProveedor() => 'Proveedor',
        $user?->esCalidad() => 'Calidad',
        $user?->esEncargadoPlanta() => 'Encargado de planta',
        $user?->esVentas() => 'Ventas',
        default => '',
    };
    $iniciales = collect(preg_split('/\s+/', trim($nombreMostrado)))
        ->filter()
        ->map(fn ($parte) => mb_strtoupper(mb_substr($parte, 0, 1)))
        ->take(2)
        ->implode('');
@endphp

<header class="sticky top-0 z-30 flex flex-wrap items-center gap-4 border-b border-text-muted/10 bg-surface/95 px-4 py-3 backdrop-blur sm:px-6">
    <button type="button" data-sidebar-toggle aria-label="Abrir menú" class="rounded-lg p-2 text-text hover:bg-text-muted/10 lg:hidden">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
    </button>

    <div class="min-w-0 flex-1">
        @if ($title !== 'Panel')
            <p class="truncate text-base font-semibold text-text">{{ $title }}</p>
        @else
        <p class="truncate text-base font-semibold text-text">Buenos {{ now()->hour < 12 ? 'días' : (now()->hour < 19 ? 'tardes' : 'noches') }}, {{ $nombreMostrado }}</p>
        <p class="truncate text-sm text-text-muted">Aquí tienes un resumen de la operación de EcoLact.</p>
        @endif
    </div>

    <p class="hidden text-sm capitalize text-text-muted md:block">{{ now()->locale('es')->translatedFormat('l, d \d\e F \d\e Y') }}</p>

    <a href="{{ route('notificaciones.index') }}" aria-label="Notificaciones" class="relative rounded-lg p-2 text-text hover:bg-text-muted/10">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg>
    </a>

    <div class="relative">
        <button type="button" data-dropdown-toggle="user-menu" class="flex items-center gap-2 rounded-lg p-1.5 hover:bg-text-muted/10">
            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-soft text-sm font-semibold text-primary-dark">
                {{ $iniciales !== '' ? $iniciales : 'EL' }}
            </span>
            <span class="hidden text-left sm:block">
                <span class="block text-sm font-medium text-text">{{ $nombreMostrado }}</span>
                <span class="block text-xs text-text-muted">{{ $rolMostrado }}</span>
            </span>
        </button>

        <div id="user-menu" data-dropdown-menu class="absolute right-0 z-40 mt-2 w-48 rounded-lg border border-text-muted/10 bg-surface p-1.5 shadow-lg">
            <p class="px-3 py-2 text-sm font-medium text-text">{{ $nombreMostrado }}</p>
            <p class="px-3 pb-2 text-xs text-text-muted">{{ $rolMostrado }}</p>

            @if ($user?->esAdministrador())
                <div class="border-t border-text-muted/10 py-1.5">
                    <p class="px-3 pb-1 text-xs font-medium uppercase tracking-wide text-text-muted">Configuración</p>
                    <a href="{{ route('admin.parametros-calidad.index') }}" class="block rounded-md px-3 py-1.5 text-sm text-text hover:bg-text-muted/10">Parámetros de calidad</a>
                    <a href="{{ route('admin.reglas-calidad.index') }}" class="block rounded-md px-3 py-1.5 text-sm text-text hover:bg-text-muted/10">Reglas de calidad</a>
                    <a href="{{ route('admin.configuracion-pagos.index') }}" class="block rounded-md px-3 py-1.5 text-sm text-text hover:bg-text-muted/10">Configuración de pagos</a>
                </div>
            @endif

            @if ($user?->esProveedor())
                <div class="border-t border-text-muted/10 py-1.5">
                    <a href="{{ route('proveedor.perfil') }}" class="block rounded-md px-3 py-1.5 text-sm text-text hover:bg-text-muted/10">Mi perfil</a>
                    <a href="{{ route('proveedor.qr') }}" class="block rounded-md px-3 py-1.5 text-sm text-text hover:bg-text-muted/10">Mi QR</a>
                </div>
            @endif

            <form method="POST" action="{{ route('logout') }}" class="border-t border-text-muted/10 pt-1.5">
                @csrf
                <button type="submit" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm text-danger hover:bg-danger/10">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4m6-4 4-4-4-4m-6 4h10" /></svg>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</header>
