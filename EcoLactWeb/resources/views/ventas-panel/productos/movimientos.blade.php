@extends('layouts.ventas-panel')

@section('title', 'Movimientos de productos')

@section('content')
    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat-card label="Movimientos" :value="$kpis['total']" />
        <x-ui.stat-card label="Entradas" :value="$kpis['entradas']" accent="success" hint="Producción" />
        <x-ui.stat-card label="Salidas" :value="$kpis['salidas']" accent="danger" hint="Ventas" />
    </div>

    <x-ui.card title="Movimientos de productos" subtitle="Consulta las entradas y salidas del inventario de productos terminados.">
        <x-slot:actions>
            <x-ui.button :href="route('ventas.productos.index')" variant="ghost" size="sm" as="a">← Stock</x-ui.button>
        </x-slot:actions>

        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="w-56">
                <x-forms.select name="producto_id" label="Producto" :options="$productosDisponibles" :value="request('producto_id')" placeholder="Todos los productos" />
            </div>
            <div class="w-48">
                <x-forms.select name="tipo" label="Tipo" :options="['INGRESO_PRODUCCION' => 'Entrada (producción)', 'VENTA' => 'Salida (venta)', 'AJUSTE_ENTRADA' => 'Ajuste (entrada)', 'AJUSTE_SALIDA' => 'Ajuste (salida)']" :value="request('tipo')" placeholder="Todos" />
            </div>
            <div class="w-40">
                <x-forms.input name="desde" type="date" label="Desde" value="{{ request('desde') }}" />
            </div>
            <div class="w-40">
                <x-forms.input name="hasta" type="date" label="Hasta" value="{{ request('hasta') }}" />
            </div>
            <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
            @if (request()->anyFilled(['producto_id', 'tipo', 'desde', 'hasta']))
                <x-ui.button :href="route('ventas.productos.movimientos')" variant="ghost" size="sm" as="a">Limpiar filtros</x-ui.button>
            @endif
        </form>

        @if (count($movimientos) === 0)
            <x-ui.empty-state title="No hay movimientos registrados" description="Los movimientos de entrada y salida aparecerán aquí cuando se registren." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Fecha</th>
                        <th class="py-2 pr-4">Producto</th>
                        <th class="py-2 pr-4">Tipo</th>
                        <th class="py-2 pr-4">Cantidad</th>
                        <th class="py-2 pl-4">Origen</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($movimientos as $movimiento)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-2 pr-4 text-text">{{ $movimiento->fecha()->format('d/m/Y H:i') }}</td>
                            <td class="py-2 pr-4 text-text">
                                <a class="hover:text-primary hover:underline" href="{{ route('ventas.productos.show', $movimiento->productoId()) }}">
                                    {{ $productosPorId[$movimiento->productoId()] ?? ('Producto #'.$movimiento->productoId()) }}
                                </a>
                            </td>
                            <td class="py-2 pr-4">
                                <x-ui.badge :variant="! $movimiento->esEntrada() ? 'danger' : 'success'">
                                    {{ $movimiento->tipo() === 'INGRESO_PRODUCCION' ? 'Producción' : ucfirst(strtolower($movimiento->tipo())) }}
                                </x-ui.badge>
                            </td>
                            <td class="py-2 pr-4 text-text">{{ ! $movimiento->esEntrada() ? '-' : '+' }}{{ number_format($movimiento->cantidad(), 2) }}</td>
                            <td class="py-2 pl-4 text-text-muted">
                                @if ($movimiento->ventaProductoId())
                                    <a class="hover:text-primary hover:underline" href="{{ route('ventas.ventas.show', $movimiento->ventaProductoId()) }}">Venta #{{ $movimiento->ventaProductoId() }}</a>
                                @elseif ($movimiento->loteProduccionId())
                                    LOTE-{{ $movimiento->loteProduccionId() }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>
@endsection
