<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Panel') · EcoLact</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background font-sans text-text antialiased">
    <a href="#contenido" class="ecolact-skip-link">Saltar al contenido</a>

    <div class="flex min-h-screen">
        <div data-sidebar-backdrop class="fixed inset-0 z-40 bg-text/40 lg:hidden"></div>

        <x-layout.sidebar>
            <x-layout.sidebar-link icon="home" :href="route('ventas.dashboard')" :active="request()->routeIs('ventas.dashboard')">Dashboard</x-layout.sidebar-link>

            <p class="ecolact-sidebar-heading">Ventas</p>

            <x-layout.sidebar-link icon="sales" :href="route('ventas.ventas.index')" :active="request()->routeIs('ventas.ventas.*')">
                Ventas
            </x-layout.sidebar-link>

            <p class="ecolact-sidebar-heading">Productos</p>

            <x-layout.sidebar-link icon="box" :href="route('ventas.productos.index')" :active="request()->routeIs('ventas.productos.index') || request()->routeIs('ventas.productos.show')">
                Stock de productos
            </x-layout.sidebar-link>
            <x-layout.sidebar-link icon="quality" :href="route('ventas.productos.movimientos')" :active="request()->routeIs('ventas.productos.movimientos')">
                Movimientos
            </x-layout.sidebar-link>

            <p class="ecolact-sidebar-heading">Clientes</p>

            <x-layout.sidebar-link icon="users" :href="route('ventas.compradores.index')" :active="request()->routeIs('ventas.compradores.*')">
                Compradores
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
