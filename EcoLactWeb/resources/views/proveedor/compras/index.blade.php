@extends('layouts.proveedor')

@section('title', 'Mis compras')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Compras" :value="$kpis['compras']" />
        <x-ui.stat-card label="Unidades adquiridas" :value="number_format($kpis['unidadesAdquiridas'], 2)" />
        <x-ui.stat-card label="Total comprado" :value="'S/ '.number_format($kpis['totalComprado'], 2)" />
        <x-ui.stat-card label="Última compra" :value="$kpis['ultimaCompra']?->fecha()?->format('d/m/Y') ?? '—'" />
    </div>

    <x-ui.card title="Mis compras" subtitle="Consulta tus compras de productos y revisa el detalle de cada operación.">
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="w-48">
                <x-forms.select name="periodo" label="Periodo" :options="['ultimos_30_dias' => 'Últimos 30 días', 'este_mes' => 'Este mes']" :value="request('periodo')" placeholder="Todo el historial" />
            </div>
            <div class="w-52">
                <x-forms.select name="producto_id" label="Producto" :options="$productosDisponibles->all()" :value="request('producto_id')" placeholder="Todos" />
            </div>
            <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
            @if (request()->anyFilled(['periodo', 'producto_id']))
                <x-ui.button :href="route('proveedor.compras.index')" variant="ghost" size="sm" as="a">Limpiar</x-ui.button>
            @endif
        </form>

        @if (count($compras) === 0)
            <x-ui.empty-state title="Aún no tienes compras" description="Las compras de productos que realices aparecerán aquí cuando sean registradas." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Fecha</th>
                        <th class="py-2 pr-4">Producto</th>
                        <th class="py-2 pr-4">Cantidad</th>
                        <th class="py-2 pr-4">Precio unitario</th>
                        <th class="py-2 pr-4">Importe</th>
                        <th class="py-2 pr-4">Forma de pago</th>
                        <th class="py-2 pl-4 text-right">Ver</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($compras as $compra)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 text-text">{{ $compra->fecha()->format('d/m/Y H:i') }}</td>
                            <td class="py-3 pr-4 text-text">{{ $productosPorId[$compra->productoId()] ?? 'Producto #'.$compra->productoId() }}</td>
                            <td class="py-3 pr-4 text-text">{{ number_format($compra->cantidad(), 2) }}</td>
                            <td class="py-3 pr-4 text-text-muted">S/ {{ number_format($compra->precioUnitario(), 2) }}</td>
                            <td class="py-3 pr-4 font-medium text-text">S/ {{ number_format($compra->importeTotal(), 2) }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $compra->formaPago() === 'DESCUENTO_PLANILLA' ? 'Descuento en planilla' : 'Contado' }}</td>
                            <td class="py-3 pl-4 text-right">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver compra" :href="route('proveedor.compras.show', $compra->id())" />
                                </x-tables.actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>
@endsection
