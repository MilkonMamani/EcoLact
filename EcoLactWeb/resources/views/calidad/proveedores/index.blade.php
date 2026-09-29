@extends('layouts.calidad')

@section('title', 'Proveedores')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Proveedores activos" :value="$datos->kpis['activos']" accent="success" />
        <x-ui.stat-card label="Proveedores inactivos" :value="$datos->kpis['inactivos']" />
        <x-ui.stat-card label="Zonas con proveedores" :value="$datos->kpis['zonasConProveedores']" />
        <x-ui.stat-card label="Requieren seguimiento" :value="$datos->kpis['requierenSeguimiento']" accent="warning" />
    </div>

    <x-ui.card title="Proveedores" subtitle="Consulta proveedores y revisa su historial de entregas y calidad.">
        <form method="GET" class="mb-4 space-y-3">
            <x-forms.input name="q" label="Buscar por nombre, apellido o DNI" value="{{ request('q') }}" />

            <div class="flex flex-wrap items-end gap-3">
                <div class="w-40">
                    <x-forms.select name="estado" label="Estado" :options="['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo']" :value="request('estado')" placeholder="Todos" />
                </div>
                <div class="w-44">
                    <x-forms.select name="zona_id" label="Zona" :options="collect($datos->zonas)->pluck('nombre', 'id')->all()" :value="request('zona_id')" placeholder="Todas" />
                </div>
                <div class="w-48">
                    <x-forms.select name="seguimiento" label="Seguimiento" :options="['con_seguimiento' => 'Requiere seguimiento', 'sin_seguimiento' => 'Sin seguimiento']" :value="request('seguimiento')" placeholder="Todos" />
                </div>
                <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
                @if (request()->anyFilled(['q', 'estado', 'zona_id', 'seguimiento']))
                    <x-ui.button :href="route('calidad.proveedores.index')" variant="ghost" size="sm" as="a">Limpiar</x-ui.button>
                @endif
            </div>
        </form>

        @if (count($datos->filas) === 0)
            <x-ui.empty-state title="No hay proveedores registrados" description="No hay proveedores que coincidan con tu búsqueda o filtros." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Proveedor</th>
                        <th class="py-2 pr-4">DNI</th>
                        <th class="py-2 pr-4">Zona</th>
                        <th class="py-2 pr-4">Teléfono</th>
                        <th class="py-2 pr-4">Advertencias</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($datos->filas as $fila)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 font-medium text-text">{{ $fila['nombre'] }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $fila['dni'] }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $fila['zonaNombre'] }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $fila['telefono'] ?? '—' }}</td>
                            <td class="py-3 pr-4 text-text">{{ $fila['advertencias'] }}</td>
                            <td class="py-3 pr-4"><x-ui.badge compact :status="$fila['estado']" /></td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver proveedor" :href="route('calidad.proveedores.show', $fila['id'])" />
                                </x-tables.actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>
@endsection
