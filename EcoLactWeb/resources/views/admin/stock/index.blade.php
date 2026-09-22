@php
    $totalProductos = collect($items)->filter(fn ($item) => $item->tipo() === 'PRODUCTO')->count();
    $totalInsumos = collect($items)->filter(fn ($item) => $item->tipo() === 'INSUMO')->count();
    $stockBajo = collect($items)->filter(fn ($item) => $item->stockBajo())->count();
    $sinStock = collect($items)->filter(fn ($item) => $item->sinStock())->count();

    $tipoFiltro = request('tipo');
    $busqueda = request('q');
    $itemsFiltrados = collect($items)
        ->when($tipoFiltro, fn ($coleccion) => $coleccion->filter(fn ($item) => $item->tipo() === $tipoFiltro))
        ->when($busqueda, fn ($coleccion) => $coleccion->filter(fn ($item) => str_contains(mb_strtolower($item->nombre()), mb_strtolower($busqueda))));
@endphp

@extends('layouts.admin')

@section('title', 'Stock')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Productos" :value="$totalProductos" />
        <x-ui.stat-card label="Insumos" :value="$totalInsumos" />
        <x-ui.stat-card label="Stock bajo" :value="$stockBajo" accent="warning" />
        <x-ui.stat-card label="Sin stock" :value="$sinStock" accent="danger" />
    </div>

    <x-ui.card title="Stock" subtitle="Control de existencias de productos e insumos.">
        <x-slot:actions>
            @can('create', \App\Infrastructure\Persistence\Models\ProductoMovimientoModel::class)
                <x-ui.button data-modal-open="modal-registrar-ajuste" size="sm" as="button" type="button">Registrar ajuste</x-ui.button>
            @endcan
        </x-slot:actions>

        <p class="mb-4 text-xs text-text-muted">El ajuste manual no debe usarse para registrar producción, ventas o consumo de insumos: esas operaciones ya tienen sus propios módulos. Úsalo solo para corregir una diferencia real de inventario.</p>

        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="w-56">
                <x-forms.input name="q" label="Buscar por nombre" value="{{ request('q') }}" />
            </div>
            <div class="w-44">
                <x-forms.select name="tipo" label="Ver inventario" :options="['PRODUCTO' => 'Productos', 'INSUMO' => 'Insumos']" :value="request('tipo')" placeholder="Todos" />
            </div>
            <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
            @if (request()->anyFilled(['q', 'tipo']))
                <x-ui.button :href="route('admin.stock.index')" variant="ghost" size="sm" as="a">Limpiar</x-ui.button>
            @endif
        </form>

        @if ($itemsFiltrados->isEmpty())
            <x-ui.empty-state title="No hay elementos en el inventario" description="Los productos e insumos registrados aparecerán aquí." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Elemento</th>
                        <th class="py-2 pr-4">Tipo</th>
                        <th class="py-2 pr-4">Unidad</th>
                        <th class="py-2 pr-4">Stock actual</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($itemsFiltrados as $item)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 text-text">{{ $item->nombre() }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ ucfirst(strtolower($item->tipo())) }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $item->unidadMedida() }}</td>
                            <td class="py-3 pr-4 text-text">
                                {{ number_format($item->cantidadActual(), 2) }}
                                @if ($item->sinStock())
                                    <span class="ml-1" title="Sin stock">🔴</span>
                                @elseif ($item->stockBajo())
                                    <span class="ml-1" title="Stock bajo">🟡</span>
                                @else
                                    <span class="ml-1" title="Disponible">🟢</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4"><x-ui.badge :status="$item->estado()" /></td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver movimiento de stock" :href="route('admin.stock.show', [strtolower($item->tipo()), $item->id()])" />
                                </x-tables.actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    <x-ui.modal id="modal-registrar-ajuste" title="Registrar ajuste de inventario">
        <form method="POST" action="{{ route('admin.stock.ajuste') }}" class="space-y-4">
            @csrf
            <x-forms.select name="tipo" label="Tipo" required :options="['PRODUCTO' => 'Producto', 'INSUMO' => 'Insumo']" data-ajuste-tipo />

            <div data-ajuste-campo="PRODUCTO">
                <x-forms.select name="elemento_id_producto" label="Producto" :options="collect($items)->filter(fn ($i) => $i->tipo() === 'PRODUCTO')->mapWithKeys(fn ($i) => [$i->id() => $i->nombre().' (stock actual: '.number_format($i->cantidadActual(), 2).')'])->all()" />
            </div>

            <div data-ajuste-campo="INSUMO" hidden>
                <x-forms.select name="elemento_id_insumo" label="Insumo" :options="collect($items)->filter(fn ($i) => $i->tipo() === 'INSUMO')->mapWithKeys(fn ($i) => [$i->id() => $i->nombre().' (stock actual: '.number_format($i->cantidadActual(), 2).')'])->all()" />
            </div>

            <x-forms.input name="nuevo_stock" type="number" step="any" label="Nuevo stock" required />
            <x-forms.input name="motivo" label="Motivo" required placeholder="Ej. Diferencia encontrada en inventario" />
            <x-forms.textarea name="observacion" label="Observación" :rows="2" />

            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button type="button" data-modal-close variant="ghost">Cancelar</x-ui.button>
                <x-ui.button type="submit">Confirmar ajuste</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

    <script>
        (function () {
            const selectTipo = document.querySelector('[data-ajuste-tipo]');
            const camposProducto = document.querySelector('[data-ajuste-campo="PRODUCTO"]');
            const camposInsumo = document.querySelector('[data-ajuste-campo="INSUMO"]');
            const selectProducto = camposProducto?.querySelector('select');
            const selectInsumo = camposInsumo?.querySelector('select');

            function actualizar() {
                const esProducto = selectTipo.value === 'PRODUCTO';
                camposProducto.hidden = ! esProducto;
                camposInsumo.hidden = esProducto;
                if (selectProducto) selectProducto.name = esProducto ? 'elemento_id' : 'elemento_id_producto';
                if (selectInsumo) selectInsumo.name = esProducto ? 'elemento_id_insumo' : 'elemento_id';
            }

            selectTipo?.addEventListener('change', actualizar);
            actualizar();
        })();
    </script>
@endsection
