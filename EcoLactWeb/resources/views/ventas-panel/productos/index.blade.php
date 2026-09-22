@extends('layouts.ventas-panel')

@section('title', 'Stock de productos')

@section('content')
    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat-card label="Productos" :value="$kpis['productos']" hint="Registrados" />
        <x-ui.stat-card label="Stock total" :value="number_format($kpis['stockTotal'], 2)" hint="Unidades disponibles" />
        <x-ui.stat-card label="Agotados" :value="$kpis['agotados']" accent="danger" />
    </div>

    <x-ui.card title="Stock de productos" subtitle="Consulta la disponibilidad actual de los productos terminados.">
        <x-slot:actions>
            <x-ui.button :href="route('ventas.productos.movimientos')" variant="secondary" size="sm" as="a">Ver movimientos</x-ui.button>
        </x-slot:actions>

        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="w-56">
                <x-forms.input name="buscar" label="Buscar producto" placeholder="Nombre del producto" value="{{ request('buscar') }}" />
            </div>
            <div class="w-48">
                <x-forms.select name="estado_stock" label="Estado de stock" :options="['disponible' => 'Disponible', 'agotado' => 'Agotado']" :value="request('estado_stock')" placeholder="Todos" />
            </div>
            <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
            @if (request()->anyFilled(['buscar', 'estado_stock']))
                <x-ui.button :href="route('ventas.productos.index')" variant="ghost" size="sm" as="a">Limpiar filtros</x-ui.button>
            @endif
        </form>

        @if (count($productos) === 0)
            <x-ui.empty-state title="No hay productos disponibles" description="Cuando planta transfiera productos terminados, aparecerán aquí." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Producto</th>
                        <th class="py-2 pr-4">Stock actual</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($productos as $producto)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 font-medium text-text">{{ $producto->nombre() }}</td>
                            <td class="py-3 pr-4 text-text">{{ number_format($producto->cantidadActual(), 2) }} {{ $producto->unidadMedida() }}</td>
                            <td class="py-3 pr-4">
                                @if ($producto->sinStock())
                                    <x-ui.badge variant="danger">Agotado</x-ui.badge>
                                @else
                                    <x-ui.badge variant="success">Disponible</x-ui.badge>
                                @endif
                            </td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver producto" :href="route('ventas.productos.show', $producto->id())" />
                                </x-tables.actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>
@endsection
