@php
    $stock = app(\App\Domain\Produccion\ProductoStockRepository::class)->buscarPorProductoId($venta->producto_id);
@endphp

@extends('layouts.ventas-panel')

@section('title', 'Venta #' . $venta->id)

@section('content')
    <x-ui.button :href="route('ventas.ventas.index')" variant="ghost" size="sm" as="a">&larr; Ventas</x-ui.button>

    <x-ui.card :title="'Venta #' . $venta->id" subtitle="Detalle del registro de venta.">
        <x-slot:actions>
            <x-ui.badge :status="$venta->estado" />
        </x-slot:actions>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-text-muted/10 p-4">
                <h3 class="mb-3 text-sm font-semibold text-text">Información de la venta</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Fecha</dt><dd class="text-text">{{ $venta->fecha->format('d/m/Y H:i') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Producto</dt><dd class="text-text">{{ $venta->producto->nombre }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Cantidad</dt><dd class="text-text">{{ number_format((float) $venta->cantidad, 2) }} {{ $venta->producto->unidad_medida }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Precio unitario</dt><dd class="text-text">S/ {{ number_format((float) $venta->precio_unitario, 2) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Importe total</dt><dd class="font-medium text-text">S/ {{ number_format((float) $venta->importe_total, 2) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Forma de pago</dt><dd class="text-text">{{ $venta->forma_pago === 'DESCUENTO_PLANILLA' ? 'Descuento en planilla' : 'Contado' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Estado</dt><dd><x-ui.badge :status="$venta->estado" /></dd></div>
                </dl>
            </div>

            <div class="rounded-xl border border-text-muted/10 p-4">
                <h3 class="mb-3 text-sm font-semibold text-text">Cliente</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Tipo</dt><dd class="text-text">{{ ucfirst(strtolower($venta->tipo_cliente)) }}</dd></div>
                    @if ($venta->tipo_cliente === 'PROVEEDOR' && $venta->proveedor)
                        <div class="flex justify-between gap-4"><dt class="text-text-muted">Nombre</dt><dd class="text-text">{{ trim($venta->proveedor->nombres.' '.$venta->proveedor->apellidos) }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-text-muted">DNI</dt><dd class="text-text">{{ $venta->proveedor->dni }}</dd></div>
                    @elseif ($venta->comprador)
                        <div class="flex justify-between gap-4"><dt class="text-text-muted">Nombre</dt><dd class="text-text">{{ $venta->comprador->nombre }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-text-muted">Documento</dt><dd class="text-text">{{ $venta->comprador->documento ?? '—' }}</dd></div>
                    @else
                        <div class="flex justify-between gap-4"><dt class="text-text-muted">Nombre</dt><dd class="text-text">Público general</dd></div>
                    @endif
                </dl>

                <h3 class="mt-4 mb-3 text-sm font-semibold text-text">Impacto en inventario</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Cantidad vendida</dt><dd class="text-text">{{ number_format((float) $venta->cantidad, 2) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Stock actual</dt><dd class="text-text">{{ $stock ? number_format($stock->cantidadActual(), 2) : '—' }}</dd></div>
                </dl>
                <div class="mt-2">
                    <x-ui.button :href="route('ventas.productos.show', $venta->producto_id)" variant="ghost" size="sm" as="a">Ver producto →</x-ui.button>
                </div>
            </div>
        </div>
    </x-ui.card>
@endsection
