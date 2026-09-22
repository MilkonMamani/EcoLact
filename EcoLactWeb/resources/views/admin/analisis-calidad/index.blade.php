@extends('layouts.admin')

@section('title', 'Calidad')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <x-ui.stat-card label="Pendientes de análisis" :value="$datos->kpis['pendientes']" hint="Entregas sin análisis confirmado" accent="warning">
            <x-slot:icon><x-ui.icon name="clock" class="h-6 w-6" /></x-slot:icon>
        </x-ui.stat-card>
        <x-ui.stat-card label="Analizadas" :value="$datos->kpis['analizadas']" accent="info">
            <x-slot:icon><x-ui.icon name="beaker" class="h-6 w-6" /></x-slot:icon>
        </x-ui.stat-card>
        <x-ui.stat-card label="Aceptadas" :value="$datos->kpis['aceptadas']" accent="success">
            <x-slot:icon><x-ui.icon name="check-circle" class="h-6 w-6" /></x-slot:icon>
        </x-ui.stat-card>
        <x-ui.stat-card label="Observadas" :value="$datos->kpis['observadas']" accent="warning">
            <x-slot:icon><x-ui.icon name="alert-circle" class="h-6 w-6" /></x-slot:icon>
        </x-ui.stat-card>
        <x-ui.stat-card label="Rechazadas" :value="$datos->kpis['rechazadas']" accent="danger" class="sm:col-span-2 lg:col-span-1">
            <x-slot:icon><x-ui.icon name="x-circle" class="h-6 w-6" /></x-slot:icon>
        </x-ui.stat-card>
    </div>

    <x-ui.card title="Calidad" subtitle="Todas las entregas y su análisis de calidad. El análisis es opcional: una entrega pendiente igual se acopia y se paga.">
        <form method="GET" class="mb-4 space-y-3">
            <x-forms.input name="q" label="Buscar proveedor, DNI o ID de entrega" value="{{ request('q') }}" placeholder="Ej. Juan Pérez, 12345678, 1045" />

            <div class="flex flex-wrap items-end gap-3">
                <div class="w-44">
                    <x-forms.select name="fecha" label="Fecha" :options="[
                        'hoy' => 'Hoy',
                        'ayer' => 'Ayer',
                        'esta_semana' => 'Esta semana',
                        'semana_anterior' => 'Semana anterior',
                        'este_mes' => 'Este mes',
                        'personalizado' => 'Personalizado',
                    ]" :value="request('fecha')" placeholder="Todas" />
                </div>

                @if (request('fecha') === 'personalizado')
                    <div class="w-40">
                        <x-forms.input name="desde" type="date" label="Desde" value="{{ request('desde') }}" />
                    </div>
                    <div class="w-40">
                        <x-forms.input name="hasta" type="date" label="Hasta" value="{{ request('hasta') }}" />
                    </div>
                @endif

                <div class="w-44">
                    <x-forms.select name="zona_id" label="Zona" :options="collect($datos->zonas)->pluck('nombre', 'id')->all()" :value="request('zona_id')" placeholder="Todas" />
                </div>

                <div class="w-44">
                    <x-forms.select name="resultado" label="Resultado" :options="['ACEPTADA' => 'Aceptada', 'OBSERVADA' => 'Observada', 'RECHAZADA' => 'Rechazada']" :value="request('resultado')" placeholder="Todos" />
                </div>

                <div class="w-44">
                    <x-forms.select name="estado" label="Estado" :options="['PENDIENTE' => 'Pendiente', 'BORRADOR' => 'Borrador', 'CONFIRMADO' => 'Confirmado']" :value="request('estado')" placeholder="Todos" />
                </div>

                <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
                @if (request()->anyFilled(['q', 'fecha', 'zona_id', 'resultado', 'estado']))
                    <x-ui.button :href="route('admin.analisis-calidad.index')" variant="ghost" size="sm" as="a">Limpiar</x-ui.button>
                @endif
            </div>
        </form>

        @if (count($datos->filas) === 0)
            <x-ui.empty-state title="No hay entregas para mostrar" description="Las entregas registradas en Acopio aparecen aquí; el análisis de calidad se registra cuando el analista muestrea al proveedor." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-[11px] font-medium uppercase tracking-wider text-label">
                        <th class="py-2 pr-4">Fecha</th>
                        <th class="py-2 pr-4">Proveedor</th>
                        <th class="py-2 pr-4">Entrega</th>
                        <th class="py-2 pr-4">Zona</th>
                        <th class="py-2 pr-4">Resultado</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($datos->filas as $fila)
                        @php($confirmado = $fila['estado'] === 'CONFIRMADO')
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 whitespace-nowrap text-text">{{ $fila['fecha'] }}</td>
                            <td class="py-3 pr-4 text-text">
                                {{ $fila['proveedorNombre'] }}
                                <span class="block text-xs text-text-muted">DNI: {{ $fila['proveedorDni'] }}</span>
                            </td>
                            <td class="py-3 pr-4 text-text-muted">
                                <a class="text-primary hover:underline" href="{{ route('admin.entregas.show', $fila['entregaId']) }}">#{{ $fila['entregaId'] }}</a>
                                <span class="block text-xs">{{ number_format($fila['litros'], 2) }} L</span>
                            </td>
                            <td class="py-3 pr-4 text-text-muted">{{ $fila['zonaNombre'] }}</td>
                            <td class="py-3 pr-4">
                                @if ($fila['resultado'])
                                    <x-ui.badge :status="$fila['resultado']" />
                                @else
                                    <span class="text-text-muted">—</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4">
                                @if ($fila['estado'] === 'PENDIENTE')
                                    <x-ui.badge status="PENDIENTE" icon="clock">Pendiente</x-ui.badge>
                                @else
                                    <x-ui.badge :status="$fila['estado']" />
                                @endif
                            </td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    @if ($confirmado)
                                        <x-ui.action-button type="view" :text="true" label="Ver" :subject="'análisis de la entrega #'.$fila['entregaId']" :href="route('admin.analisis-calidad.show', $fila['analisisId'])" />
                                    @else
                                        <x-ui.action-button type="analyze" :text="true" label="Analizar" :subject="'entrega #'.$fila['entregaId']" :href="route('admin.entregas.analisis-calidad.index', $fila['entregaId'])" />
                                    @endif
                                </x-tables.actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>
@endsection
