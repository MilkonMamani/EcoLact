@extends('layouts.admin')

@section('title', $detalle->nombre)

@section('content')
    <x-ui.button :href="route('admin.stock.index')" variant="ghost" size="sm" as="a">&larr; Stock</x-ui.button>

    <x-ui.card :title="$detalle->nombre" :subtitle="ucfirst(strtolower($detalle->tipo))">
        <x-slot:actions>
            <x-ui.badge :status="$detalle->estado" />
        </x-slot:actions>

        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Unidad</dt>
                <dd class="mt-1 text-sm text-text">{{ $detalle->unidadMedida }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Stock actual</dt>
                <dd class="mt-1 text-sm text-text">{{ number_format($detalle->cantidadActual, 2) }} {{ $detalle->unidadMedida }}</dd>
            </div>
            @if ($detalle->stockMinimo !== null)
                <div>
                    <dt class="text-xs uppercase tracking-wide text-text-muted">Stock mínimo</dt>
                    <dd class="mt-1 text-sm text-text">{{ number_format($detalle->stockMinimo, 2) }} {{ $detalle->unidadMedida }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-text-muted">Disponibilidad</dt>
                    <dd class="mt-1 text-sm">
                        @if ($detalle->cantidadActual <= 0.0)
                            <span class="text-danger">🔴 Sin stock</span>
                        @elseif ($detalle->cantidadActual < $detalle->stockMinimo)
                            <span class="text-warning">🟡 Stock bajo</span>
                        @else
                            <span class="text-success">🟢 Normal</span>
                        @endif
                    </dd>
                </div>
            @endif
        </dl>

        <div class="mt-6">
            <h3 class="mb-3 text-sm font-semibold text-text">Movimientos recientes</h3>

            @if (count($detalle->movimientos) === 0)
                <x-ui.empty-state title="Sin movimientos registrados" :icon="false" />
            @else
                <x-tables.table>
                    <thead>
                        <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                            <th class="py-2 pr-4">Fecha</th>
                            <th class="py-2 pr-4">Tipo</th>
                            <th class="py-2 pr-4">Cantidad</th>
                            <th class="py-2 pr-4">Origen / Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($detalle->movimientos as $movimiento)
                            <tr class="border-b border-text-muted/5 last:border-0">
                                <td class="py-2 pr-4 whitespace-nowrap text-text">{{ $movimiento['fecha'] }}</td>
                                <td class="py-2 pr-4"><x-ui.badge :status="$movimiento['tipo']" /></td>
                                <td class="py-2 pr-4 text-text">
                                    {{ in_array($movimiento['tipo'], ['SALIDA', 'VENTA', 'AJUSTE_SALIDA'], true) ? '-' : '+' }}{{ number_format($movimiento['cantidad'], 2) }}
                                </td>
                                <td class="py-2 pr-4 text-text-muted">{{ $movimiento['origen'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-tables.table>
            @endif
        </div>
    </x-ui.card>
@endsection
