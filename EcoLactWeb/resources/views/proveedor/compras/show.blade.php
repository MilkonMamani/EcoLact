@extends('layouts.proveedor')

@section('title', 'Compra #' . $venta->id)

@section('content')
    <x-ui.button :href="route('proveedor.compras.index')" variant="ghost" size="sm" as="a">&larr; Volver a mis compras</x-ui.button>

    <x-ui.card :title="'Compra #' . $venta->id" subtitle="Detalle de la operación.">
        <x-slot:actions>
            <x-ui.badge :status="$venta->estado" />
        </x-slot:actions>

        <div class="mb-4 flex items-center justify-between rounded-lg bg-primary-soft px-4 py-3">
            <span class="text-sm font-medium text-primary-dark">Total</span>
            <span class="text-xl font-semibold text-primary-dark">S/ {{ number_format((float) $venta->importe_total, 2) }}</span>
        </div>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Fecha</dt>
                <dd class="mt-1 text-sm text-text">{{ $venta->fecha->format('d/m/Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Producto</dt>
                <dd class="mt-1 text-sm text-text">{{ $venta->producto->nombre }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Cantidad</dt>
                <dd class="mt-1 text-sm text-text">{{ number_format((float) $venta->cantidad, 2) }} {{ $venta->producto->unidad_medida }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Precio unitario</dt>
                <dd class="mt-1 text-sm text-text">S/ {{ number_format((float) $venta->precio_unitario, 2) }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Forma de pago</dt>
                <dd class="mt-1 text-sm text-text">{{ $venta->forma_pago === 'DESCUENTO_PLANILLA' ? 'Descuento en planilla' : 'Contado' }}</dd>
            </div>
        </dl>

        @if ($liquidacionRelacionada)
            <div class="mt-4 rounded-xl border border-text-muted/10 p-4">
                <p class="text-sm text-text">Esta compra fue descontada de tu liquidación del periodo correspondiente.</p>
                <div class="mt-2">
                    <x-ui.button :href="route('proveedor.liquidaciones.show', $liquidacionRelacionada->id())" variant="ghost" size="sm" as="a">Ver liquidación →</x-ui.button>
                </div>
            </div>
        @endif
    </x-ui.card>
@endsection
