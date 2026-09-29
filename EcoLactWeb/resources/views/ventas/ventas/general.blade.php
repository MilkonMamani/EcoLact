@extends('layouts.admin')

@section('title', 'Ventas')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Ventas" :value="$datos->kpis['ventas']" hint="Del periodo seleccionado" />
        <x-ui.stat-card label="Total vendido" :value="'S/ '.number_format($datos->kpis['totalVendido'], 2)" accent="accent" />
        <x-ui.stat-card label="Productos vendidos" :value="number_format($datos->kpis['productosVendidos'], 2)" />
        <x-ui.stat-card label="Compradores" :value="$datos->kpis['compradores']" />
    </div>

    <x-ui.card title="Ventas" subtitle="Registro y control de ventas de productos.">
        <x-slot:actions>
            @can('create', \App\Infrastructure\Persistence\Models\VentaProductoModel::class)
                <x-ui.button data-modal-open="modal-nueva-venta" size="sm" as="button" type="button">+ Nueva venta</x-ui.button>
            @endcan
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
                    <x-ui.button :href="route('admin.ventas.index')" variant="ghost" size="sm" as="a">Limpiar</x-ui.button>
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
                                    <x-ui.action-button type="view" label="Ver venta" :href="route('admin.ventas.show', $fila['id'])" />
                                </x-tables.actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    <x-ui.modal id="modal-nueva-venta" title="Nueva venta">
        <form method="POST" action="{{ route('admin.ventas.store') }}" class="space-y-4">
            @csrf
            <x-forms.select name="tipo_cliente" label="Tipo de cliente" required :options="['PROVEEDOR' => 'Proveedor', 'MAYORISTA' => 'Mayorista', 'PUBLICO' => 'Público']" data-venta-tipo-cliente />

            <div data-venta-campo="PROVEEDOR" hidden>
                <x-forms.input name="proveedor_id" type="number" label="ID de proveedor" />
            </div>

            <div data-venta-campo="MAYORISTA PUBLICO" hidden>
                <x-forms.select name="comprador_id" label="Comprador (opcional)" :options="collect($compradores)->mapWithKeys(fn ($c) => [$c->id() => $c->nombre()])->all()" placeholder="Sin comprador específico" />
                <x-ui.button type="button" data-modal-open="modal-nuevo-comprador" variant="ghost" size="sm" class="mt-1">+ Nuevo comprador</x-ui.button>
            </div>

            <x-forms.select name="producto_id" label="Producto" required :options="collect($productos)->mapWithKeys(fn ($p) => [$p->id() => $p->nombre()])->all()" />
            <x-forms.input name="cantidad" type="number" step="any" label="Cantidad" required />
            <x-forms.select name="forma_pago" label="Forma de pago" required :options="['CONTADO' => 'Contado', 'DESCUENTO_PLANILLA' => 'Descuento en planilla']" />

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Confirmar venta</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <x-ui.modal id="modal-nuevo-comprador" title="Nuevo comprador">
        <form method="POST" action="{{ route('admin.compradores.store') }}" class="space-y-4">
            @csrf
            <x-forms.input name="nombre" label="Nombre" required />
            <x-forms.input name="documento" label="Documento" />
            <x-forms.input name="telefono" label="Teléfono" />
            <x-forms.select name="tipo_cliente" label="Tipo de cliente" required :options="['MAYORISTA' => 'Mayorista', 'PUBLICO' => 'Público']" />
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Guardar</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <script>
        (function () {
            const selectTipoCliente = document.querySelector('[data-venta-tipo-cliente]');
            const campos = document.querySelectorAll('[data-venta-campo]');

            function actualizar() {
                const tipo = selectTipoCliente.value;
                campos.forEach((campo) => {
                    const tiposVisibles = campo.getAttribute('data-venta-campo').split(' ');
                    campo.hidden = ! tiposVisibles.includes(tipo);
                });
            }

            selectTipoCliente?.addEventListener('change', actualizar);
            actualizar();
        })();
    </script>
@endsection
