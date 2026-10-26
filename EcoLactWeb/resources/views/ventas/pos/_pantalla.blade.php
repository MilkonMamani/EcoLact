{{--
    VENTAS / POS — pantalla compartida por ADMINISTRADOR (admin.ventas.*) y PERSONAL DE VENTAS
    (ventas.ventas.*); solo cambia $portal (prefijo de rutas). Dos pestañas:
    - Nueva venta: quién compra → producto → cantidad → forma de pago, con el resumen a la
      derecha. El navegador solo PREVISUALIZA (ventas-pos.js); RegistrarVentaProducto vuelve a
      resolver precio, tipo, total, stock y caja al confirmar.
    - Historial: tabla compacta con filtros GET (?tab=historial); "Ver" abre el detalle en un
      panel lateral (fetch a la ruta show). Sin JS, show pinta esta misma pantalla con el panel.
--}}
@php
    $rutas = [
        'index' => route($portal.'.ventas.index'),
        'show' => route($portal.'.ventas.show', '__ID__'),
        'saldo' => route($portal.'.ventas.saldo-proveedor', '__ID__'),
        'comprador' => route($portal.'.compradores.store'),
    ];
@endphp

<div class="fichas vpos" data-ventas-pos data-url-show="{{ $rutas['show'] }}" data-url-saldo="{{ $rutas['saldo'] }}" data-url-comprador="{{ $rutas['comprador'] }}">
    <header class="vpos-header">
        @if ($portal === 'admin')
            <button type="button" data-sidebar-toggle aria-label="Abrir menú" class="rounded-lg p-2 text-text transition hover:bg-text-muted/10 lg:hidden">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
            </button>
        @endif
        <div class="min-w-0">
            <h1 class="fichas-title">Ventas / POS</h1>
            <p class="fichas-subtitle">Registra ventas de productos terminados y consulta las operaciones realizadas.</p>
        </div>
    </header>

    <nav class="vpos-tabs" role="tablist" aria-label="Ventas">
        <a href="{{ $rutas['index'] }}" role="tab" id="vpos-tab-nueva" aria-controls="vpos-panel-nueva" aria-selected="{{ $tab === 'nueva' ? 'true' : 'false' }}" data-vpos-tab="nueva">Nueva venta</a>
        <a href="{{ $rutas['index'] }}?tab=historial" role="tab" id="vpos-tab-historial" aria-controls="vpos-panel-historial" aria-selected="{{ $tab === 'historial' ? 'true' : 'false' }}" data-vpos-tab="historial">Historial</a>
    </nav>

    <section id="vpos-panel-nueva" role="tabpanel" aria-labelledby="vpos-tab-nueva" data-vpos-panel="nueva" @if ($tab !== 'nueva') hidden @endif>
        @include('ventas.pos._venta-form')
    </section>

    <section id="vpos-panel-historial" role="tabpanel" aria-labelledby="vpos-tab-historial" data-vpos-panel="historial" @if ($tab !== 'historial') hidden @endif>
        @include('ventas.pos._historial')
    </section>

    {{-- ------------------------------------------------------------ detalle (panel lateral) --}}
    <div class="caja-velo" data-vpos-velo @if (! $seleccion) hidden @endif></div>
    <aside @class(['caja-panel', 'is-abierto' => $seleccion]) data-vpos-detalle aria-label="Detalle de la venta" aria-live="polite" @if (! $seleccion) aria-hidden="true" @endif>
        @if ($seleccion)
            @include('ventas.pos._detalle', ['venta' => $seleccion])
        @endif
    </aside>

    <template data-vpos-detalle-skeleton>
        <x-fichas.panel-skeleton />
    </template>

    @include('ventas.pos._comprador-modal')
</div>
