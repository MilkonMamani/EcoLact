@extends('layouts.calidad')

@section('title', 'Seleccionar muestra')

@php
    $fechas = ['hoy' => 'Hoy', 'ayer' => 'Ayer', 'semana' => 'Últimos 7 días'];
    $hora = fn (?string $iso): string => $iso ? \Illuminate\Support\Carbon::parse($iso)->format('d/m/Y H:i') : '—';
@endphp

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-text">Seleccionar muestra</h1>
            <p class="text-sm text-text-muted">Calidad no analiza todas las entregas: elige cuáles serán evaluadas.</p>
        </div>
        <x-ui.button :href="route('calidad.analisis-calidad.index')" variant="ghost" size="sm" as="a">Ver muestras pendientes →</x-ui.button>
    </div>

    <x-ui.card title="Método de selección">
        <div class="flex flex-wrap gap-3">
            <x-ui.button :href="route('calidad.muestreo.index', ['modo' => 'aleatoria', 'fecha' => $fecha])" :variant="$modo === 'aleatoria' ? 'primary' : 'secondary'" as="a">Elegir al azar</x-ui.button>
            <x-ui.button :href="route('calidad.muestreo.index', ['modo' => 'manual', 'fecha' => $fecha])" :variant="$modo === 'manual' ? 'primary' : 'secondary'" as="a">Seleccionar manualmente</x-ui.button>
        </div>
        <p class="mt-3 text-xs text-text-muted">La muestra al azar la elige el servidor entre las entregas disponibles. ¿Estás en campo con el proveedor? Usa <a class="text-primary hover:underline" href="{{ route('calidad.entregas.index') }}">Muestreo directo</a> desde Entregas.</p>
    </x-ui.card>

    @if ($modo === 'aleatoria')
        <x-ui.card title="Muestra seleccionada" subtitle="Método: Aleatoria">
            @if ($propuesta === null)
                <x-ui.empty-state title="No hay entregas disponibles para muestrear" description="Todas las entregas del periodo ya fueron muestreadas o analizadas." :icon="false" />
            @else
                @php($e = $propuesta['entrega'])
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                    <div><dt class="text-xs uppercase tracking-wide text-text-muted">Proveedor</dt><dd class="font-medium text-text">{{ $e['proveedorNombre'] }}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-text-muted">DNI</dt><dd class="text-text">{{ $e['proveedorDni'] }}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-text-muted">Litros</dt><dd class="font-medium text-text">{{ number_format($e['litros'], 2) }} L</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-text-muted">Zona</dt><dd class="text-text">{{ $e['zonaNombre'] ?? '—' }}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-text-muted">Acopiador</dt><dd class="text-text">{{ $e['acopiadorNombre'] ?? '—' }}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-text-muted">Fecha/hora</dt><dd class="text-text">{{ $hora($e['fechaHora']) }}</dd></div>
                </dl>

                <div class="mt-5 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('calidad.muestreo.store') }}">
                        @csrf
                        <input type="hidden" name="entrega_id" value="{{ $e['entregaId'] }}">
                        <input type="hidden" name="metodo_seleccion" value="ALEATORIA">
                        <input type="hidden" name="propuesta_token" value="{{ $propuesta['propuesta_token'] }}">
                        <x-ui.button type="submit">Confirmar muestra</x-ui.button>
                    </form>
                    <x-ui.button :href="route('calidad.muestreo.index', ['modo' => 'aleatoria', 'fecha' => $fecha, 'excluir' => [...$vistas, $e['entregaId']]])" variant="secondary" as="a">Elegir otra</x-ui.button>
                </div>
            @endif
        </x-ui.card>
    @elseif ($modo === 'manual')
        <x-ui.card title="Entregas disponibles para muestreo" subtitle="Solo entregas no anuladas, sin análisis confirmado y sin muestra previa.">
            <form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
                <input type="hidden" name="modo" value="manual">
                <div class="min-w-48 flex-1">
                    <x-forms.input name="q" label="Buscar proveedor o DNI" value="{{ request('q') }}" />
                </div>
                <div class="w-44">
                    <x-forms.select name="zona_id" label="Zona" :options="collect($zonas)->pluck('nombre', 'id')->all()" :value="request('zona_id')" placeholder="Todas" />
                </div>
                <div class="w-44">
                    <x-forms.select name="fecha" label="Fecha" :options="$fechas" :value="$fecha" />
                </div>
                <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
            </form>

            @if (count($elegibles) === 0)
                <x-ui.empty-state title="No hay entregas disponibles para muestrear" :icon="false" />
            @else
                <x-tables.table>
                    <thead>
                        <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                            <th class="py-2 pr-4">Proveedor</th>
                            <th class="py-2 pr-4">Litros</th>
                            <th class="py-2 pr-4">Zona</th>
                            <th class="py-2 pr-4">Acopiador</th>
                            <th class="py-2 pr-4">Fecha/hora</th>
                            <th class="py-2 pl-4 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($elegibles as $e)
                            <tr class="border-b border-text-muted/5 last:border-0">
                                <td class="py-3 pr-4 text-text">
                                    {{ $e['proveedorNombre'] }}
                                    <span class="block text-xs text-text-muted">DNI: {{ $e['proveedorDni'] }}</span>
                                </td>
                                <td class="py-3 pr-4 font-medium text-text">{{ number_format($e['litros'], 2) }} L</td>
                                <td class="py-3 pr-4 text-text-muted">{{ $e['zonaNombre'] ?? '—' }}</td>
                                <td class="py-3 pr-4 text-text-muted">{{ $e['acopiadorNombre'] ?? '—' }}</td>
                                <td class="py-3 pr-4 whitespace-nowrap text-text">{{ $hora($e['fechaHora']) }}</td>
                                <td class="py-3 pl-4 text-right">
                                    <form method="POST" action="{{ route('calidad.muestreo.store') }}" class="inline-flex items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="entrega_id" value="{{ $e['entregaId'] }}">
                                        <input type="hidden" name="metodo_seleccion" value="MANUAL">
                                        <select name="tipo_control" aria-label="Tipo de control" class="rounded-lg border border-text-muted/20 bg-surface px-2 py-1 text-xs text-text">
                                            <option value="RUTINARIO">Rutinario</option>
                                            <option value="DIRIGIDO">Dirigido</option>
                                        </select>
                                        <x-ui.button type="submit" size="sm">Seleccionar</x-ui.button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-tables.table>
            @endif
        </x-ui.card>
    @endif
@endsection
