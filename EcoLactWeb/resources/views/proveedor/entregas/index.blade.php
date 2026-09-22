@extends('layouts.proveedor')

@section('title', 'Mis entregas')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Entregas" :value="$kpis['entregas']" />
        <x-ui.stat-card label="Litros" :value="number_format($kpis['litros'], 2).' L'" />
        <x-ui.stat-card label="Analizadas" :value="$kpis['analizadas']" accent="success" />
        <x-ui.stat-card label="Pendientes" :value="$kpis['pendientes']" accent="warning" />
    </div>

    <x-ui.card title="Mis entregas" subtitle="Historial de leche que has entregado a EcoLact.">
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="w-44">
                <x-forms.select name="periodo" label="Periodo" :options="[
                    'hoy' => 'Hoy',
                    'esta_semana' => 'Esta semana',
                    'este_mes' => 'Este mes',
                    'personalizado' => 'Personalizado',
                ]" :value="request('periodo')" placeholder="Todas" />
            </div>
            @if (request('periodo') === 'personalizado')
                <div class="w-40">
                    <x-forms.input name="desde" type="date" label="Desde" value="{{ request('desde') }}" />
                </div>
                <div class="w-40">
                    <x-forms.input name="hasta" type="date" label="Hasta" value="{{ request('hasta') }}" />
                </div>
            @endif
            <div class="w-44">
                <x-forms.select name="estado" label="Estado" :options="['PENDIENTE_ANALISIS' => 'Pendiente de análisis', 'ANALIZADA' => 'Analizada']" :value="request('estado')" placeholder="Todos" />
            </div>
            <div class="w-44">
                <x-forms.select name="resultado" label="Resultado" :options="['ACEPTADA' => 'Aceptada', 'OBSERVADA' => 'Observada', 'RECHAZADA' => 'Rechazada', 'sin_resultado' => 'Sin resultado']" :value="request('resultado')" placeholder="Todos" />
            </div>
            <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
            @if (request()->anyFilled(['periodo', 'estado', 'resultado']))
                <x-ui.button :href="route('proveedor.entregas.index')" variant="ghost" size="sm" as="a">Limpiar</x-ui.button>
            @endif
        </form>

        @if (count($entregas) === 0)
            <x-ui.empty-state title="No tienes entregas registradas" description="Cuando un acopiador registre una entrega a tu nombre, aparecerá aquí." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Fecha</th>
                        <th class="py-2 pr-4">Litros</th>
                        <th class="py-2 pr-4">Método</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pr-4">Resultado</th>
                        <th class="py-2 pl-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entregas as $entrega)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 text-text">{{ $entrega->fechaHora()->format('d/m/Y H:i') }}</td>
                            <td class="py-3 pr-4 font-medium text-text">{{ number_format(($resultadosPorEntrega[$entrega->id()] ?? null) === 'RECHAZADA' ? 0.0 : $entrega->litros(), 2) }} L</td>
                            <td class="py-3 pr-4 text-text-muted">{{ $entrega->metodoIdentificacion() }}</td>
                            <td class="py-3 pr-4"><x-ui.badge :status="$entrega->estado()->value" /></td>
                            <td class="py-3 pr-4">
                                @if ($resultadosPorEntrega[$entrega->id()] ?? null)
                                    <x-ui.badge :status="$resultadosPorEntrega[$entrega->id()]" />
                                @else
                                    <span class="text-xs text-text-muted">—</span>
                                @endif
                            </td>
                            <td class="py-3 pl-4">
                                <x-tables.actions>
                                    <x-ui.action-button type="view" label="Ver entrega" :href="route('proveedor.entregas.show', $entrega->id())" />
                                </x-tables.actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>
@endsection
