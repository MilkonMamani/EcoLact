@extends('layouts.acopiador')

@section('title', 'Proveedores de mi zona')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Proveedores" :value="$kpis['proveedores']" hint="En mi zona" />
        <x-ui.stat-card label="Activos" :value="$kpis['activos']" accent="success" />
        <x-ui.stat-card label="Inactivos" :value="$kpis['inactivos']" />
        <x-ui.stat-card label="Atendidos" :value="$kpis['atendidos']" hint="Este periodo" />
    </div>

    <x-ui.card title="Proveedores de mi zona" subtitle="Consulta los proveedores registrados en tu zona y su información para la atención de entregas.">
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="w-56">
                <x-forms.input name="buscar" label="Buscar" placeholder="Nombre, apellido o DNI" value="{{ request('buscar') }}" />
            </div>
            <div class="w-44">
                <x-forms.select name="periodo" label="Periodo (atendidos)" :options="[
                    'hoy' => 'Hoy',
                    'esta_semana' => 'Esta semana',
                    'semana_anterior' => 'Semana anterior',
                    'este_mes' => 'Este mes',
                    'personalizado' => 'Personalizado',
                ]" :value="request('periodo', 'esta_semana')" />
            </div>
            @if (request('periodo') === 'personalizado')
                <div class="w-40">
                    <x-forms.input name="desde" type="date" label="Desde" value="{{ request('desde') }}" />
                </div>
                <div class="w-40">
                    <x-forms.input name="hasta" type="date" label="Hasta" value="{{ request('hasta') }}" />
                </div>
            @endif
            <div class="w-40">
                <x-forms.select name="estado" label="Estado" :options="['ACTIVO' => 'Activo', 'INACTIVO' => 'Inactivo']" :value="request('estado')" placeholder="Todos" />
            </div>
            <div class="w-44">
                <x-forms.select name="actividad" label="Actividad" :options="['con_entregas' => 'Con entregas', 'sin_entregas' => 'Sin entregas']" :value="request('actividad')" placeholder="Todos" />
            </div>
            <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
            @if (request()->anyFilled(['buscar', 'periodo', 'estado', 'actividad']))
                <x-ui.button :href="route('acopiador.proveedores.index')" variant="ghost" size="sm" as="a">Limpiar filtros</x-ui.button>
            @endif
        </form>

        @if (count($proveedores) === 0)
            <x-ui.empty-state title="No hay proveedores en tu zona" description="Cuando se registren proveedores en tu zona aparecerán aquí." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Proveedor</th>
                        <th class="py-2 pr-4">DNI</th>
                        <th class="py-2 pr-4">Teléfono</th>
                        <th class="py-2 pr-4">Entregas</th>
                        <th class="py-2 pr-4">Última entrega</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($proveedores as $proveedor)
                        @php $resumen = $resumenEntregas[$proveedor->id()] ?? null; @endphp
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 font-medium text-text">{{ $proveedor->nombreCompleto() }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ substr($proveedor->dni(), 0, 2) }}****{{ substr($proveedor->dni(), -2) }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $proveedor->telefono() ?? '—' }}</td>
                            <td class="py-3 pr-4 text-text">{{ $resumen->entregas ?? 0 }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $resumen?->ultima_entrega ? \Illuminate\Support\Carbon::parse($resumen->ultima_entrega)->format('d/m/Y') : 'Sin entregas' }}</td>
                            <td class="py-3 pr-4"><x-ui.badge :status="$proveedor->estado()->value" /></td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver proveedor" :href="route('acopiador.proveedores.show', $proveedor->id())" />
                                </x-tables.actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>
@endsection
