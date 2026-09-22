@extends('layouts.acopiador')

@section('title', 'Entregas')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Entregas" :value="$kpis['entregas']" hint="Registradas" />
        <x-ui.stat-card label="Litros" :value="number_format($kpis['litros'], 2).' L'" hint="Recibidos" />
        <x-ui.stat-card label="Proveedores" :value="$kpis['proveedores']" hint="Atendidos" />
        <x-ui.stat-card label="Pendientes" :value="$kpis['pendientes']" hint="De análisis" accent="warning" />
    </div>

    <x-ui.card title="Entregas" subtitle="Consulta y gestiona las entregas de leche registradas en tu zona.">
        <x-slot:actions>
            <x-ui.button :href="route('acopiador.entregas.create')" size="sm" as="a">+ Registrar entrega</x-ui.button>
        </x-slot:actions>

        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
            <div class="w-44">
                <x-forms.select name="periodo" label="Periodo" :options="[
                    'hoy' => 'Hoy',
                    'ayer' => 'Ayer',
                    'esta_semana' => 'Esta semana',
                    'semana_anterior' => 'Semana anterior',
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
            <div class="w-48">
                <x-forms.select name="proveedor_id" label="Proveedor" :options="$proveedoresDisponibles" :value="request('proveedor_id')" placeholder="Todos los proveedores" />
            </div>
            <div class="w-44">
                <x-forms.select name="estado" label="Estado" :options="['PENDIENTE_ANALISIS' => 'Pendiente de análisis', 'ANALIZADA' => 'Analizada']" :value="request('estado')" placeholder="Todos" />
            </div>
            <div class="w-44">
                <x-forms.select name="resultado" label="Resultado" :options="['ACEPTADA' => 'Aceptada', 'OBSERVADA' => 'Observada', 'RECHAZADA' => 'Rechazada', 'sin_resultado' => 'Sin resultado']" :value="request('resultado')" placeholder="Todos" />
            </div>
            <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
            @if (request()->anyFilled(['periodo', 'proveedor_id', 'estado', 'resultado']))
                <x-ui.button :href="route('acopiador.entregas.index')" variant="ghost" size="sm" as="a">Limpiar filtros</x-ui.button>
            @endif
        </form>

        @if (count($entregas) === 0)
            <x-ui.empty-state title="Aún no hay entregas" description="No tienes entregas registradas para este periodo." />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Fecha/hora</th>
                        <th class="py-2 pr-4">Proveedor</th>
                        <th class="py-2 pr-4">Litros</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pr-4">Calidad</th>
                        <th class="py-2 pl-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entregas as $entrega)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-3 pr-4 text-text">{{ $entrega->fechaHora()->format('d/m/Y H:i') }}</td>
                            <td class="py-3 pr-4 text-text-muted">
                                <a class="hover:text-primary hover:underline" href="{{ route('acopiador.proveedores.show', $entrega->proveedorId()) }}">
                                    {{ $proveedoresPorId[$entrega->proveedorId()] ?? ('Proveedor #'.$entrega->proveedorId()) }}
                                </a>
                            </td>
                            <td class="py-3 pr-4 font-medium text-text">{{ number_format($entrega->litros(), 2) }} L</td>
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
                                    <x-ui.action-button type="view" label="Ver entrega" :href="route('acopiador.entregas.show', $entrega->id())" />
                                </x-tables.actions>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>
@endsection
