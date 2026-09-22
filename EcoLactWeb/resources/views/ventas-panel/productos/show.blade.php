@extends('layouts.ventas-panel')

@section('title', $detalle->nombre)

@section('content')
    <x-ui.button :href="route('ventas.productos.index')" variant="ghost" size="sm" as="a">&larr; Stock de productos</x-ui.button>

    <x-ui.card :title="$detalle->nombre" subtitle="Stock disponible para venta.">
        <x-slot:actions>
            @if ($detalle->cantidadActual <= 0.0)
                <x-ui.badge variant="danger">Agotado</x-ui.badge>
            @else
                <x-ui.badge variant="success">Disponible</x-ui.badge>
            @endif
        </x-slot:actions>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Unidad</dt>
                <dd class="mt-1 text-sm text-text">{{ $detalle->unidadMedida }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Stock actual</dt>
                <dd class="mt-1 text-sm text-text">{{ number_format($detalle->cantidadActual, 2) }} {{ $detalle->unidadMedida }}</dd>
            </div>
        </dl>
    </x-ui.card>

    <x-ui.card title="Movimientos del producto" subtitle="Trazabilidad de entradas (producción terminada y ajustes) y salidas (ventas).">
        @if (count($detalle->movimientos) === 0)
            <x-ui.empty-state title="Sin movimientos registrados" description="Este producto todavía no tiene movimientos de inventario." :icon="false" />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Fecha</th>
                        <th class="py-2 pr-4">Tipo</th>
                        <th class="py-2 pr-4">Cantidad</th>
                        <th class="py-2 pr-4">Origen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($detalle->movimientos as $movimiento)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-2 pr-4 whitespace-nowrap text-text">{{ $movimiento['fecha'] }}</td>
                            <td class="py-2 pr-4">
                                <x-ui.badge :variant="in_array($movimiento['tipo'], ['VENTA', 'AJUSTE_SALIDA'], true) ? 'danger' : 'success'">
                                    {{ $movimiento['tipo'] === 'INGRESO_PRODUCCION' ? 'Producción' : ucfirst(strtolower($movimiento['tipo'])) }}
                                </x-ui.badge>
                            </td>
                            <td class="py-2 pr-4 text-text">
                                {{ in_array($movimiento['tipo'], ['VENTA', 'AJUSTE_SALIDA'], true) ? '-' : '+' }}{{ number_format($movimiento['cantidad'], 2) }}
                            </td>
                            <td class="py-2 pr-4 text-text-muted">{{ $movimiento['origen'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>
@endsection
