@extends('layouts.ventas-panel')

@section('title', 'Ventas')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Ventas" :value="$datos->kpis['ventas']" hint="Del periodo seleccionado" />
        <x-ui.stat-card label="Total vendido" :value="'S/ '.number_format($datos->kpis['totalVendido'], 2)" accent="accent" />
        <x-ui.stat-card label="Productos vendidos" :value="number_format($datos->kpis['productosVendidos'], 2)" />
        <x-ui.stat-card label="Compradores" :value="$datos->kpis['compradores']" />
    </div>

    <x-ui.card title="Historial de ventas" subtitle="Consulta las ventas registradas y revisa el detalle de cada operación.">
        <x-slot:actions>
            <x-ui.button :href="route('ventas.ventas.create')" size="sm" as="a">Registrar venta</x-ui.button>
        </x-slot:actions>

        <form method="GET" class="mb-4 space-y-3">
            <div class="flex flex-wrap items-end gap-3">
                <div class="w-44">
                    <x-forms.select name="periodo" label="Periodo" :options="[
                        'hoy' => 'Hoy',
                        'esta_semana' => 'Esta semana',
                        'este_mes' => 'Este mes',
                        'personalizado' => 'Personalizado',
                    ]" :value="request('periodo', 'esta_semana')" :placeholder="null" />
                </div>

                @if (request('periodo') === 'personalizado')
                    <div class="w-40">
                        <x-forms.input name="desde" type="date" label="Desde" value="{{ request('desde') }}" />
                    </div>
                    <div class="w-40">
                        <x-forms.input name="hasta" type="date" label="Hasta" value="{{ request('hasta') }}" />
                    </div>
                @endif

                <div class="w-56">
                    <x-forms.input name="q" label="Buscar producto o cliente" value="{{ request('q') }}" />
                </div>

                <div class="w-44">
                    <x-forms.select name="tipo_cliente" label="Tipo de cliente" :options="['PROVEEDOR' => 'Proveedor', 'MAYORISTA' => 'Mayorista', 'PUBLICO' => 'Público']" :value="request('tipo_cliente')" placeholder="Todos" />
                </div>

                <div class="w-44">
                    <x-forms.select name="forma_pago" label="Forma de pago" :options="['CONTADO' => 'Contado', 'DESCUENTO_PLANILLA' => 'Descuento en planilla']" :value="request('forma_pago')" placeholder="Todas" />
                </div>

                <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
                @if (request()->anyFilled(['periodo', 'q', 'tipo_cliente', 'forma_pago']))
                    <x-ui.button :href="route('ventas.ventas.index')" variant="ghost" size="sm" as="a">Limpiar</x-ui.button>
                @endif
            </div>
        </form>

        @if (count($datos->filas) === 0)
            <x-ui.empty-state title="No hay ventas" description="Aún no se han registrado ventas de productos en el periodo seleccionado." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Fecha</th>
                        <th class="py-2 pr-4">Producto</th>
                        <th class="py-2 pr-4">Cliente</th>
                        <th class="py-2 pr-4">Cantidad</th>
                        <th class="py-2 pr-4">Importe</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($datos->filas as $fila)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 whitespace-nowrap text-text">{{ $fila['fecha'] }}</td>
                            <td class="py-3 pr-4 text-text">{{ $fila['productoNombre'] }}</td>
                            <td class="py-3 pr-4 text-text">
                                {{ $fila['clienteNombre'] }}
                                <span class="block text-xs text-text-muted">{{ ucfirst(strtolower($fila['clienteTipo'])) }}</span>
                            </td>
                            <td class="py-3 pr-4 text-text-muted">{{ number_format($fila['cantidad'], 2) }}</td>
                            <td class="py-3 pr-4 font-medium text-text">S/ {{ number_format($fila['importeTotal'], 2) }}</td>
                            <td class="py-3 pr-4"><x-ui.badge compact :status="$fila['estado']" /></td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver venta" :href="route('ventas.ventas.show', $fila['id'])" />
                                </x-tables.actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>
@endsection
