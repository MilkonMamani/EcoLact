@extends('layouts.calidad')

@section('title', 'Dashboard')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-text">Dashboard</h1>
            <p class="text-sm text-text-muted">Resumen de la actividad de Calidad.</p>
        </div>
        <x-ui.button :href="route('calidad.muestreo.index')" size="sm" as="a">+ Seleccionar muestra</x-ui.button>
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3">
        <div class="w-44">
            <x-forms.select name="periodo" label="Periodo" :options="[
                'hoy' => 'Hoy',
                'ayer' => 'Ayer',
                'esta_semana' => 'Esta semana',
                'semana_anterior' => 'Semana anterior',
                'este_mes' => 'Este mes',
                'personalizado' => 'Personalizado',
            ]" :value="request('periodo')" placeholder="Todo el historial" />
        </div>
        @if (request('periodo') === 'personalizado')
            <div class="w-40">
                <x-forms.input name="desde" type="date" label="Desde" value="{{ request('desde') }}" />
            </div>
            <div class="w-40">
                <x-forms.input name="hasta" type="date" label="Hasta" value="{{ request('hasta') }}" />
            </div>
        @endif
        <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
    </form>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.stat-card label="Muestras pendientes" :value="$dashboard->muestrasPendientes" accent="warning" hint="Seleccionadas, por analizar" />
        <x-ui.stat-card label="Analizadas" :value="$dashboard->kpisPeriodo['analizados']" :hint="'Hoy: '.$dashboard->analizadasHoy.' · Aceptadas '.$dashboard->kpisPeriodo['aceptados'].' · Observadas '.$dashboard->kpisPeriodo['observados'].' · Rechazadas '.$dashboard->kpisPeriodo['rechazados']" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card title="Muestras pendientes" subtitle="Entregas seleccionadas como muestra que falta analizar.">
            <x-slot:actions>
                <x-ui.button :href="route('calidad.analisis-calidad.index')" variant="ghost" size="sm" as="a">Ver todos →</x-ui.button>
            </x-slot:actions>

            @if (count($dashboard->muestrasFilas) === 0)
                <x-ui.empty-state title="No tienes muestras pendientes" description="Selecciona una entrega como muestra para analizarla." :icon="false" />
            @else
                <x-tables.table>
                    <thead>
                        <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                            <th class="py-2 pr-4">Fecha</th>
                            <th class="py-2 pr-4">Proveedor</th>
                            <th class="py-2 pr-4">Litros</th>
                            <th class="py-2 pr-4">Método</th>
                            <th class="py-2 pl-4 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dashboard->muestrasFilas as $fila)
                            <tr class="border-b border-text-muted/5 last:border-0">
                                <td class="py-2 pr-4 whitespace-nowrap text-text">{{ \Illuminate\Support\Carbon::parse($fila['fechaHora'])->format('d/m/Y H:i') }}</td>
                                <td class="py-2 pr-4 text-text">{{ $fila['proveedorNombre'] }}</td>
                                <td class="py-2 pr-4 text-text">{{ number_format($fila['litros'], 2) }} L</td>
                                <td class="py-2 pr-4 text-text-muted">{{ ucfirst(strtolower($fila['metodoSeleccion'])) }}</td>
                                <td class="py-2 pl-4 text-right"><a class="text-primary hover:underline" href="{{ route('calidad.entregas.analisis.index', $fila['entregaId']) }}">Analizar →</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-tables.table>
            @endif
        </x-ui.card>

        <x-ui.card title="Últimos análisis" subtitle="Análisis registrados recientemente.">
            <x-slot:actions>
                <x-ui.button :href="route('calidad.analisis-calidad.index', ['tab' => 'historial'])" variant="ghost" size="sm" as="a">Ver historial →</x-ui.button>
            </x-slot:actions>

            @if (count($dashboard->ultimosAnalisisFilas) === 0)
                <x-ui.empty-state title="Aún no hay análisis registrados" :icon="false" />
            @else
                <x-tables.table>
                    <thead>
                        <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                            <th class="py-2 pr-4">Fecha</th>
                            <th class="py-2 pr-4">Proveedor</th>
                            <th class="py-2 pr-4">Resultado</th>
                            <th class="py-2 pl-4 text-right">Ver</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dashboard->ultimosAnalisisFilas as $fila)
                            <tr class="border-b border-text-muted/5 last:border-0">
                                <td class="py-2 pr-4 whitespace-nowrap text-text">{{ $fila['fecha'] }}</td>
                                <td class="py-2 pr-4 text-text">{{ $fila['proveedorNombre'] }}</td>
                                <td class="py-2 pr-4">
                                    @if ($fila['resultado'])
                                        <x-ui.badge compact :status="$fila['resultado']" />
                                    @else
                                        <span class="text-xs text-text-muted">—</span>
                                    @endif
                                </td>
                                <td class="py-2 pl-4 text-right"><a class="text-primary hover:underline" href="{{ route('calidad.analisis-calidad.show', $fila['id']) }}">Ver →</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-tables.table>
            @endif
        </x-ui.card>
    </div>

    <x-ui.card title="Comunicados recientes">
        <x-slot:actions>
            <x-ui.button :href="route('calidad.comunicados.index')" variant="ghost" size="sm" as="a">Ver todos →</x-ui.button>
        </x-slot:actions>

        @if (count($dashboard->comunicadosRecientes) === 0)
            <x-ui.empty-state title="No hay comunicados" :icon="false" />
        @else
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ($dashboard->comunicadosRecientes as $comunicado)
                    <a href="{{ route('calidad.comunicados.show', $comunicado->id()) }}" class="block rounded-xl border border-text-muted/10 bg-surface p-3 shadow-sm transition-shadow hover:shadow-md">
                        <x-ui.badge variant="info">{{ $comunicado->tipo() }}</x-ui.badge>
                        <p class="mt-2 text-sm font-medium text-text">{{ $comunicado->titulo() }}</p>
                        <p class="mt-1 text-xs text-text-muted">{{ $comunicado->fechaPublicacion()->format('d/m/Y') }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </x-ui.card>
@endsection
