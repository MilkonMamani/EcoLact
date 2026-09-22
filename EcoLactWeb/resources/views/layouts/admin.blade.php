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
<body class="min-h-screen bg-background font-sans text-text antialiased @yield('body-class')">
    <a href="#contenido" class="ecolact-skip-link">Saltar al contenido</a>

    <div class="flex min-h-screen">
        <div data-sidebar-backdrop class="fixed inset-0 z-40 bg-text/40 lg:hidden"></div>

        <x-layout.sidebar>
            <x-layout.sidebar-link icon="home" :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">Inicio</x-layout.sidebar-link>

            <p class="ecolact-sidebar-heading">Operación</p>
            <x-layout.sidebar-link icon="milk" :href="route('admin.entregas.index')" :active="request()->routeIs('admin.entregas.*')">Acopio</x-layout.sidebar-link>
            <x-layout.sidebar-link icon="users" :href="route('admin.proveedores.index')" :active="request()->routeIs('admin.proveedores.*')">Proveedores</x-layout.sidebar-link>
            <x-layout.sidebar-link icon="users" :href="route('admin.acopiadores.index')" :active="request()->routeIs('admin.acopiadores.*')">Acopiadores</x-layout.sidebar-link>
            <x-layout.sidebar-link icon="pin" :href="route('admin.zonas.index')" :active="request()->routeIs('admin.zonas.*')">Zonas</x-layout.sidebar-link>

            <p class="ecolact-sidebar-heading">Calidad</p>
            <x-layout.sidebar-link icon="quality" :href="route('admin.analisis-calidad.index')" :active="request()->routeIs('admin.analisis-calidad.*')">Calidad</x-layout.sidebar-link>

            <p class="ecolact-sidebar-heading">Configuración</p>
            <x-layout.sidebar-link icon="quality" :href="route('admin.parametros-calidad.index')" :active="request()->routeIs('admin.parametros-calidad.*')">Parámetros de calidad</x-layout.sidebar-link>
            <x-layout.sidebar-link icon="quality" :href="route('admin.reglas-calidad.index')" :active="request()->routeIs('admin.reglas-calidad.*')">Reglas de calidad</x-layout.sidebar-link>
            <x-layout.sidebar-link icon="payment" :href="route('admin.configuracion-pagos.index')" :active="request()->routeIs('admin.configuracion-pagos.*')">Configuración de pagos</x-layout.sidebar-link>

            <p class="ecolact-sidebar-heading">Finanzas</p>
            <x-layout.sidebar-group label="Pagos" icon="payment" :active="request()->routeIs('admin.pagos.*')">
                <x-layout.sidebar-link :href="route('admin.pagos.proveedores.index')" :active="request()->routeIs('admin.pagos.proveedores.*')">Pagar a proveedores</x-layout.sidebar-link>
                <x-layout.sidebar-link :href="route('admin.pagos.trabajadores.index')" :active="request()->routeIs('admin.pagos.trabajadores.*') || request()->routeIs('admin.pagos.index')">Pagar a trabajadores</x-layout.sidebar-link>
            </x-layout.sidebar-group>

            <p class="ecolact-sidebar-heading">Producción</p>
            <x-layout.sidebar-link icon="audit" :href="route('admin.recetas.index')" :active="request()->routeIs('admin.recetas.*')">Recetas</x-layout.sidebar-link>
            <x-layout.sidebar-link icon="production" :href="route('admin.produccion.index')" :active="request()->routeIs('admin.produccion.*')">Producción</x-layout.sidebar-link>

            <p class="ecolact-sidebar-heading">Inventario</p>
            <x-layout.sidebar-link icon="box" :href="route('admin.insumos.index')" :active="request()->routeIs('admin.insumos.*')">Insumos</x-layout.sidebar-link>
            <x-layout.sidebar-link icon="box" :href="route('admin.productos.index')" :active="request()->routeIs('admin.productos.*') || request()->routeIs('admin.producto-precios.*') || request()->routeIs('admin.stock.*')">Productos</x-layout.sidebar-link>

            <p class="ecolact-sidebar-heading">Ventas</p>
            <x-layout.sidebar-link icon="sales" :href="route('admin.ventas.index')" :active="request()->routeIs('admin.ventas.index')">Ventas</x-layout.sidebar-link>
            <x-layout.sidebar-link icon="users" :href="route('admin.compradores.index')" :active="request()->routeIs('admin.compradores.*')">Compradores</x-layout.sidebar-link>

            <p class="ecolact-sidebar-heading">Comunicación</p>
            <x-layout.sidebar-link icon="chat" :href="route('admin.comunicados.index')" :active="request()->routeIs('admin.comunicados.*')">Comunicados</x-layout.sidebar-link>

            <p class="ecolact-sidebar-heading">Administración</p>
            <x-layout.sidebar-link icon="users" :href="route('admin.encargados-planta.index')" :active="request()->routeIs('admin.encargados-planta.*')">Encargados de planta</x-layout.sidebar-link>
            <x-layout.sidebar-link icon="users" :href="route('admin.personal-ventas.index')" :active="request()->routeIs('admin.personal-ventas.*')">Personal de ventas</x-layout.sidebar-link>
            <x-layout.sidebar-link icon="users" :href="route('admin.analistas-calidad.index')" :active="request()->routeIs('admin.analistas-calidad.*')">Analistas de calidad</x-layout.sidebar-link>
            {{-- Pendiente: la tabla auditorias existe pero ningún Use Case escribe todavía en ella. --}}
            <x-layout.sidebar-link icon="audit" disabled>Auditoría</x-layout.sidebar-link>
        </x-layout.sidebar>

        <div class="flex min-w-0 flex-1 flex-col">
            <main id="contenido" class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto @yield('contenedor', 'max-w-7xl') space-y-6">
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

