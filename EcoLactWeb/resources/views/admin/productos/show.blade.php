@extends('layouts.admin')

@section('title', $detalle->nombre)

@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $unidades = $detalle->unidadMedida === 'unidad' ? 'unidades' : $detalle->unidadMedida;
@endphp

@section('content')
    <x-layout.breadcrumbs :items="[['Inventario', null], ['Productos', route('admin.productos.index')], [$detalle->nombre, null]]" />

    {{-- Ficha de CONSULTA: no se edita aquí. La fórmula y la imagen se gestionan desde la receta. --}}
    <x-ui.card :title="$detalle->nombreBase" :subtitle="$detalle->tipo ?? 'Producto terminado'">
        <x-slot:actions>
            @if ($detalle->estado === 'DISPONIBLE')
                <x-ui.badge status="DISPONIBLE">Disponible</x-ui.badge>
            @elseif ($detalle->estado === 'SIN_STOCK')
                <x-ui.badge status="SIN_STOCK">Sin stock</x-ui.badge>
            @else
                <x-ui.badge status="INACTIVO">Inactivo</x-ui.badge>
            @endif
        </x-slot:actions>

        <div class="flex flex-col gap-6 sm:flex-row">
            <x-ui.producto-imagen :url="$detalle->imagenUrl" :alt="$detalle->nombre" size="lg" />

            <dl class="grid flex-1 gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-text-muted">Presentación</dt>
                    <dd class="mt-1 text-sm text-text">{{ $detalle->presentacion ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-text-muted">Stock actual</dt>
                    <dd class="mt-1 text-lg font-semibold text-text">{{ $fmt($detalle->stockActual) }} {{ $unidades }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-text-muted">Receta</dt>
                    <dd class="mt-1 text-sm text-text">
                        @if ($detalle->receta)
                            {{ $detalle->receta['nombre'] }} <span class="text-xs text-text-muted">v{{ $detalle->receta['version'] }} · {{ $detalle->receta['estado'] === 'ACTIVA' ? 'activa' : 'inactiva' }}</span>
                        @else
                            Sin receta
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-text-muted">Última producción</dt>
                    <dd class="mt-1 text-sm text-text">{{ $detalle->ultimaProduccion ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        <div class="mt-6 flex flex-wrap gap-2 border-t border-text-muted/10 pt-4">
            @if ($detalle->receta)
                <x-ui.button :href="route('admin.recetas.index', ['producto' => $detalle->id])" variant="secondary" size="sm" as="a">Ver receta</x-ui.button>
            @else
                <x-ui.button :href="route('admin.recetas.index', ['configurar' => $detalle->id])" size="sm" as="a">Configurar receta</x-ui.button>
            @endif
            <x-ui.button :href="route('admin.producto-precios.index', $detalle->id)" variant="ghost" size="sm" as="a">Precios de venta</x-ui.button>
        </div>
    </x-ui.card>

    <x-ui.card title="Últimas producciones" subtitle="Lotes en los que se fabricó este producto.">
        @if (count($detalle->producciones) === 0)
            <x-ui.empty-state title="Todavía no se produjo" description="Cuando se finalice una producción, su cantidad real aparecerá aquí." :icon="false" />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Lote</th>
                        <th class="py-2 pr-4">Inicio</th>
                        <th class="py-2 pr-4">Planificado</th>
                        <th class="py-2 pr-4">Obtenido</th>
                        <th class="py-2 pr-4">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($detalle->producciones as $produccion)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-2 pr-4"><a href="{{ route('admin.produccion.show', $produccion['loteId']) }}" class="font-mono text-xs text-primary hover:underline">{{ $produccion['codigo'] }}</a></td>
                            <td class="py-2 pr-4 whitespace-nowrap text-text-muted">{{ $produccion['fecha'] }}</td>
                            <td class="py-2 pr-4 text-text">{{ $fmt($produccion['planificado']) }}</td>
                            <td class="py-2 pr-4 font-medium text-text">{{ $produccion['obtenido'] !== null ? $fmt($produccion['obtenido']) : 'Pendiente' }}</td>
                            <td class="py-2 pr-4"><x-ui.badge :status="$produccion['estado']" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    <x-ui.card title="Historial de movimientos" subtitle="Ingresos por producción, ventas y ajustes. El stock es la suma de estos movimientos.">
        @if (count($detalle->movimientos) === 0)
            <x-ui.empty-state title="Sin movimientos" description="Este producto todavía no tiene stock: aumenta al finalizar una producción." :icon="false" />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Fecha</th>
                        <th class="py-2 pr-4">Movimiento</th>
                        <th class="py-2 pr-4">Cantidad</th>
                        <th class="py-2 pr-4">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($detalle->movimientos as $movimiento)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-2 pr-4 whitespace-nowrap text-text-muted">{{ $movimiento['fecha'] }}</td>
                            <td class="py-2 pr-4 text-text">{{ $movimiento['tipo'] }}</td>
                            <td class="py-2 pr-4 font-semibold {{ $movimiento['entrada'] ? 'text-success' : 'text-danger' }}">{{ $movimiento['entrada'] ? '+' : '−' }}{{ $fmt($movimiento['cantidad']) }}</td>
                            <td class="py-2 pr-4 text-text-muted">{{ $movimiento['detalle'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>
@endsection
