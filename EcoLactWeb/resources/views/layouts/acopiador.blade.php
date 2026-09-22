<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="google-maps-api-key" content="{{ config('services.google_maps.api_key') }}">
    <meta name="google-maps-map-id" content="{{ config('services.google_maps.map_id') }}">
    <title>@yield('title', 'Panel') · EcoLact</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background font-sans text-text antialiased">
    <a href="#contenido" class="ecolact-skip-link">Saltar al contenido</a>

    <div class="flex min-h-screen">
        <div data-sidebar-backdrop class="fixed inset-0 z-40 bg-text/40 lg:hidden"></div>

        <x-layout.sidebar>
            <x-layout.sidebar-link icon="home" :href="route('acopiador.dashboard')" :active="request()->routeIs('acopiador.dashboard')">Dashboard</x-layout.sidebar-link>

            <p class="ecolact-sidebar-heading">Operación</p>

            <x-layout.sidebar-link icon="pin" :href="route('acopiador.recorridos.index')" :active="request()->routeIs('acopiador.recorridos.*')">
                Mis recorridos
            </x-layout.sidebar-link>
            <x-layout.sidebar-link icon="milk" :href="route('acopiador.entregas.index')" :active="request()->routeIs('acopiador.entregas.*')">
                Entregas
            </x-layout.sidebar-link>
            <x-layout.sidebar-link icon="users" :href="route('acopiador.proveedores.index')" :active="request()->routeIs('acopiador.proveedores.*')">
                Proveedores de mi zona
            </x-layout.sidebar-link>

            <p class="ecolact-sidebar-heading">Seguimiento</p>

            <x-layout.sidebar-link icon="quality" :href="route('acopiador.seguimiento-calidad.index')" :active="request()->routeIs('acopiador.seguimiento-calidad.*')">
                Seguimiento de calidad
            </x-layout.sidebar-link>

            <p class="ecolact-sidebar-heading">Comunicación</p>

            <x-layout.sidebar-link icon="chat" :href="route('acopiador.comunicados.index')" :active="request()->routeIs('acopiador.comunicados.*')">
                Comunicados
            </x-layout.sidebar-link>

            <p class="ecolact-sidebar-heading">Sistema</p>

            <x-layout.sidebar-link icon="bell" :href="route('notificaciones.index')" :active="request()->routeIs('notificaciones.*')">
                Notificaciones
            </x-layout.sidebar-link>
        </x-layout.sidebar>

        <div class="flex min-w-0 flex-1 flex-col">
            <x-layout.topbar />

            <main id="contenido" class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-7xl space-y-6">
            <x-layout.flash-messages />

            @yield('content')
                </div>
            </main>

            <footer class="border-t border-text-muted/10 px-4 py-4 text-center text-xs text-text-muted sm:px-6">
                EcoLact &middot; {{ now()->year }} &middot; Del campo a tu mesa
            </footer>
        </div>
    </div>
</body>
</html>
