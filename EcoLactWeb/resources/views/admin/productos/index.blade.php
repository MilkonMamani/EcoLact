@extends('layouts.admin')

@section('title', 'Productos')

@section('content')
    <x-layout.breadcrumbs :items="[['Inventario', null], ['Productos', null]]" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Productos" :value="$datos->kpis['total']" accent="primary" />
        <x-ui.stat-card label="Disponibles" :value="$datos->kpis['disponibles']" accent="success" hint="Con stock" />
        <x-ui.stat-card label="Sin stock" :value="$datos->kpis['sinStock']" accent="warning" />
        <x-ui.stat-card label="Sin receta" :value="$datos->kpis['sinReceta']" accent="danger" hint="Aún no se pueden producir" />
    </div>

    {{-- Inventario de productos terminados: sin "+ Nuevo producto" ni acciones de edición. Nacen desde su receta. --}}
    <x-ui.card title="Productos" subtitle="Productos terminados generados desde las recetas y disponibles después de la producción.">
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="flex-1">
                <x-forms.input name="q" label="Buscar por nombre o tipo" value="{{ request('q') }}" :sticky="false" />
            </div>
            <div class="w-48">
                <x-forms.select name="estado" label="Estado" :options="['DISPONIBLE' => 'Disponible', 'SIN_STOCK' => 'Sin stock']" :value="request('estado')" placeholder="Todos" :sticky="false" />
            </div>
            <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
            @if (request()->anyFilled(['q', 'estado']))
                <x-ui.button :href="route('admin.productos.index')" variant="ghost" size="sm" as="a">Limpiar</x-ui.button>
            @endif
        </form>

        @if (count($datos->filas) === 0)
            <x-ui.empty-state title="Todavía no hay productos" description="Los productos se crean solos al guardar una receta: ve a Producción → Recetas." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Imagen</th>
                        <th class="py-2 pr-4">Producto</th>
                        <th class="hidden py-2 pr-4 md:table-cell">Presentación</th>
                        <th class="py-2 pr-4">Stock actual</th>
                        <th class="py-2 pr-4">Receta</th>
                        <th class="hidden py-2 pr-4 lg:table-cell">Última producción</th>
                        <th class="py-2 pr-4">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($datos->filas as $fila)
                        <tr class="border-b border-text-muted/5 transition-colors last:border-0 hover:bg-primary-soft/40">
                            <td class="py-3 pr-4"><x-ui.producto-imagen :url="$fila['imagenUrl']" :alt="$fila['nombre']" /></td>
                            <td class="py-3 pr-4">
                                <a href="{{ route('admin.productos.show', $fila['id']) }}" class="font-medium text-text hover:text-primary hover:underline">{{ $fila['nombreBase'] }}</a>
                                @if ($fila['tipo'])
                                    <p class="text-xs text-text-muted">{{ $fila['tipo'] }}</p>
                                @endif
                            </td>
                            <td class="hidden py-3 pr-4 whitespace-nowrap text-text md:table-cell">{{ $fila['presentacion'] ?? '—' }}</td>
                            <td class="py-3 pr-4 whitespace-nowrap font-medium text-text">{{ rtrim(rtrim(number_format($fila['stockActual'], 2), '0'), '.') }} {{ $fila['unidadMedida'] === 'unidad' ? 'unidades' : $fila['unidadMedida'] }}</td>
                            <td class="py-3 pr-4">
                                @if ($fila['recetaEstado'] === 'CONFIGURADA')
                                    <x-ui.badge compact status="CONFIGURADA">Configurada</x-ui.badge>
                                @elseif ($fila['recetaEstado'] === 'INACTIVA')
                                    <x-ui.badge compact status="INACTIVA">Inactiva</x-ui.badge>
                                @else
                                    <x-ui.badge compact status="SIN_RECETA">Sin receta</x-ui.badge>
                                    <a href="{{ route('admin.recetas.index', ['configurar' => $fila['id']]) }}" class="ml-1 text-xs font-medium text-primary hover:underline">Configurar</a>
                                @endif
                            </td>
                            <td class="hidden py-3 pr-4 whitespace-nowrap text-text-muted lg:table-cell">{{ $fila['ultimaProduccion'] ?? '—' }}</td>
                            <td class="py-3 pr-4">
                                @if ($fila['estado'] === 'DISPONIBLE')
                                    <x-ui.badge compact status="DISPONIBLE">Disponible</x-ui.badge>
                                @elseif ($fila['estado'] === 'SIN_STOCK')
                                    <x-ui.badge compact status="SIN_STOCK">Sin stock</x-ui.badge>
                                @else
                                    <x-ui.badge compact status="INACTIVO">Inactivo</x-ui.badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>
@endsection
