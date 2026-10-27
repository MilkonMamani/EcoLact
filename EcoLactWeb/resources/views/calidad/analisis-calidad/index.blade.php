@extends('layouts.calidad')

@section('title', 'Análisis de calidad')

@section('content')
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.stat-card label="Muestras pendientes" :value="$kpisMuestreo['pendientes']" accent="warning" />
        <x-ui.stat-card label="Analizadas hoy" :value="$kpisMuestreo['analizadas_hoy']" accent="success" />
    </div>

    <x-ui.card title="Muestras y análisis" subtitle="Calidad trabaja por muestreo: solo se analizan las entregas seleccionadas.">
        <x-slot:actions>
            <x-ui.button :href="route('calidad.muestreo.index')" size="sm" as="a">+ Seleccionar muestra</x-ui.button>
        </x-slot:actions>

        <div class="mb-4 flex gap-1 border-b border-text-muted/10">
            <a href="{{ route('calidad.analisis-calidad.index', ['tab' => 'pendientes']) }}" class="rounded-t-lg px-3 py-2 text-sm font-medium {{ $tab === 'pendientes' ? 'border-b-2 border-primary text-primary' : 'text-text-muted hover:bg-text-muted/5' }}">Muestras pendientes</a>
            <a href="{{ route('calidad.analisis-calidad.index', ['tab' => 'historial']) }}" class="rounded-t-lg px-3 py-2 text-sm font-medium {{ $tab === 'historial' ? 'border-b-2 border-primary text-primary' : 'text-text-muted hover:bg-text-muted/5' }}">Historial</a>
        </div>

        @if ($tab === 'pendientes')
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                <input type="hidden" name="tab" value="pendientes">
                <div class="flex-1">
                    <x-forms.input name="q" label="Buscar proveedor o DNI" value="{{ request('q') }}" />
                </div>
                <div class="w-44">
                    <x-forms.select name="zona_id" label="Zona" :options="collect($zonas)->pluck('nombre', 'id')->all()" :value="request('zona_id')" placeholder="Todas" />
                </div>
                <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
            </form>

            @if (count($muestras) === 0)
                <x-ui.empty-state title="No tienes muestras pendientes" description="Las entregas no seleccionadas no se analizan. Selecciona una muestra para empezar." />
            @else
                <x-tables.table>
                    <thead>
                        <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                            <th class="py-2 pr-4">Entrega</th>
                            <th class="py-2 pr-4">Proveedor</th>
                            <th class="py-2 pr-4">Litros</th>
                            <th class="py-2 pr-4">Zona</th>
                            <th class="py-2 pr-4">Muestra</th>
                            <th class="py-2 pl-4 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($muestras as $fila)
                            <tr class="border-b border-text-muted/5 last:border-0">
                                <td class="py-3 pr-4 whitespace-nowrap text-text">
                                    {{ \Illuminate\Support\Carbon::parse($fila['fechaHora'])->format('d/m/Y H:i') }}
                                    <span class="block text-xs text-text-muted">#{{ $fila['entregaId'] }}</span>
                                </td>
                                <td class="py-3 pr-4 text-text">
                                    {{ $fila['proveedorNombre'] }}
                                    <span class="block text-xs text-text-muted">DNI: {{ $fila['proveedorDni'] }}</span>
                                </td>
                                <td class="py-3 pr-4 text-text">{{ number_format($fila['litros'], 2) }} L</td>
                                <td class="py-3 pr-4 text-text-muted">{{ $fila['zonaNombre'] }}</td>
                                <td class="py-3 pr-4 text-text-muted">
                                    <x-ui.badge compact :status="$fila['estado']" />
                                    {{ ucfirst(strtolower($fila['metodoSeleccion'])) }}
                                    <span class="block text-xs">{{ \Illuminate\Support\Carbon::parse($fila['fechaSeleccion'])->format('d/m H:i') }}</span>
                                </td>
                                <td class="py-3 pl-4 text-right"><a class="text-primary hover:underline" href="{{ route('calidad.entregas.analisis.index', $fila['entregaId']) }}">Analizar →</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-tables.table>
            @endif
        @else
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                <input type="hidden" name="tab" value="historial">
                <div class="flex-1">
                    <x-forms.input name="q2" label="Buscar proveedor, DNI o código de entrega" value="{{ request('q2') }}" />
                </div>
                <div class="w-40">
                    <x-forms.select name="zona_id2" label="Zona" :options="collect($historial->zonas)->pluck('nombre', 'id')->all()" :value="request('zona_id2')" placeholder="Todas" />
                </div>
                <div class="w-40">
                    <x-forms.select name="resultado" label="Resultado" :options="['ACEPTADA' => 'Aceptada', 'OBSERVADA' => 'Observada', 'RECHAZADA' => 'Rechazada']" :value="request('resultado')" placeholder="Todos" />
                </div>
                <div class="w-40">
                    <x-forms.select name="estado" label="Estado" :options="['BORRADOR' => 'Borrador', 'CONFIRMADO' => 'Confirmado']" :value="request('estado')" placeholder="Todos" />
                </div>
                <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
            </form>

            @if (count($historial->filas) === 0)
                <x-ui.empty-state title="Aún no hay análisis registrados en este periodo" />
            @else
                <x-tables.table>
                    <thead>
                        <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                            <th class="py-2 pr-4">Fecha</th>
                            <th class="py-2 pr-4">Entrega</th>
                            <th class="py-2 pr-4">Proveedor</th>
                            <th class="py-2 pr-4">Zona</th>
                            <th class="py-2 pr-4">Resultado</th>
                            <th class="py-2 pr-4">Estado</th>
                            <th class="py-2 pl-4 text-right">Ver</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($historial->filas as $fila)
                            <tr class="border-b border-text-muted/5 last:border-0">
                                <td class="py-3 pr-4 whitespace-nowrap text-text">{{ $fila['fecha'] }}</td>
                                <td class="py-3 pr-4 text-text-muted">#{{ $fila['entregaId'] }}</td>
                                <td class="py-3 pr-4 text-text">
                                    {{ $fila['proveedorNombre'] }}
                                    <span class="block text-xs text-text-muted">DNI: {{ $fila['proveedorDni'] }}</span>
                                </td>
                                <td class="py-3 pr-4 text-text-muted">{{ $fila['zonaNombre'] }}</td>
                                <td class="py-3 pr-4">
                                    @if ($fila['resultado'])
                                        <x-ui.badge compact :status="$fila['resultado']" />
                                    @else
                                        <span class="text-xs text-text-muted">—</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4"><x-ui.badge compact :status="$fila['estado']" /></td>
                                <td class="py-3 pl-4 text-right"><a class="text-primary hover:underline" href="{{ route('calidad.analisis-calidad.show', $fila['id']) }}">Ver →</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-tables.table>
            @endif
        @endif
    </x-ui.card>
@endsection
