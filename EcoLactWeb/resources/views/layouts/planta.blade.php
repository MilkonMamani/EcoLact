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
            <x-layout.sidebar-link icon="home" :href="route('planta.dashboard')" :active="request()->routeIs('planta.dashboard')">Dashboard</x-layout.sidebar-link>

            <p class="ecolact-sidebar-heading">Producción</p>

            <x-layout.sidebar-link icon="production" :href="route('planta.produccion.index')" :active="request()->routeIs('planta.produccion.*')">
                Producción
            </x-layout.sidebar-link>
            <x-layout.sidebar-link icon="audit" :href="route('planta.recetas.index')" :active="request()->routeIs('planta.recetas.*')">
                Recetas
            </x-layout.sidebar-link>

            <p class="ecolact-sidebar-heading">Insumos</p>

            <x-layout.sidebar-link icon="quality" :href="route('planta.insumos.index')" :active="request()->routeIs('planta.insumos.index') || request()->routeIs('planta.insumos.show')">
                Inventario
            </x-layout.sidebar-link>
            <x-layout.sidebar-link icon="sales" :href="route('planta.insumos.movimientos')" :active="request()->routeIs('planta.insumos.movimientos')">
                Movimientos
            </x-layout.sidebar-link>

            <p class="ecolact-sidebar-heading">Productos</p>

            <x-layout.sidebar-link icon="box" :href="route('planta.productos.index')" :active="request()->routeIs('planta.productos.*')">
                Stock de productos
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
