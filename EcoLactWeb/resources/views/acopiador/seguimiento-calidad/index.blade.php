@extends('layouts.acopiador')

@section('title', 'Seguimiento de calidad')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Pendientes" :value="$kpis['pendientes']" hint="Requieren análisis" accent="warning" />
        <x-ui.stat-card label="Analizadas" :value="$kpis['analizadas']" hint="Entregas evaluadas" />
        <x-ui.stat-card label="Aceptadas" :value="$kpis['aceptadas']" hint="Resultado favorable" accent="success" />
        <x-ui.stat-card label="Con observaciones" :value="$kpis['conObservaciones']" hint="Observadas o rechazadas" accent="warning" />
    </div>

    <x-ui.card title="Seguimiento de calidad" subtitle="Consulta el estado y resultado de calidad de las entregas registradas en tu zona.">
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="w-44">
                <x-forms.select name="periodo" label="Periodo" :options="[
                    'hoy' => 'Hoy',
                    'ayer' => 'Ayer',
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
            <div class="w-48">
                <x-forms.select name="estado_analisis" label="Estado de análisis" :options="['PENDIENTE_ANALISIS' => 'Pendiente de análisis', 'ANALIZADA' => 'Analizada']" :value="request('estado_analisis')" placeholder="Todos" />
            </div>
            <div class="w-44">
                <x-forms.select name="resultado" label="Resultado" :options="['ACEPTADA' => 'Aceptada', 'OBSERVADA' => 'Observada', 'RECHAZADA' => 'Rechazada', 'sin_resultado' => 'Sin resultado']" :value="request('resultado')" placeholder="Todos" />
            </div>
            <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
            @if (request()->anyFilled(['periodo', 'estado_analisis', 'resultado']))
                <x-ui.button :href="route('acopiador.seguimiento-calidad.index')" variant="ghost" size="sm" as="a">Limpiar filtros</x-ui.button>
            @endif
        </form>

        @if (count($entregas) === 0)
            <x-ui.empty-state title="No tienes entregas pendientes de análisis" description="Todas las entregas registradas en el periodo seleccionado ya cuentan con seguimiento de calidad." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Fecha/hora</th>
                        <th class="py-2 pr-4">Proveedor</th>
                        <th class="py-2 pr-4">Litros</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pr-4">Resultado</th>
                        <th class="py-2 pr-4">Observación</th>
                        <th class="py-2 pl-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entregas as $entrega)
                        @php $analisis = $analisisPorEntrega->get($entrega->id()); @endphp
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 text-text">{{ $entrega->fechaHora()->format('d/m/Y H:i') }}</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $proveedoresPorId[$entrega->proveedorId()] ?? ('Proveedor #'.$entrega->proveedorId()) }}</td>
                            <td class="py-3 pr-4 font-medium text-text">{{ number_format($entrega->litros(), 2) }} L</td>
                            <td class="py-3 pr-4"><x-ui.badge compact :status="$entrega->estado()->value" /></td>
                            <td class="py-3 pr-4">
                                @if ($analisis?->resultado())
                                    <x-ui.badge compact :status="$analisis->resultado()->value" />
                                @else
                                    <span class="text-xs text-text-muted">Sin resultado</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-text-muted">{{ $analisis?->observacion() ?? '—' }}</td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver seguimiento de calidad" :href="route('acopiador.seguimiento-calidad.show', $entrega->id())" />
                                </x-tables.actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>
@endsection
