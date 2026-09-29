@extends('layouts.planta')

@section('title', 'Dashboard')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-text">Dashboard</h1>
            <p class="text-sm text-text-muted">Aquí tienes el resumen de la operación de planta.</p>
        </div>
        <x-ui.button :href="route('planta.produccion.create')" as="a">Registrar producción</x-ui.button>
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3">
        <div class="w-48">
            <x-forms.select name="periodo" label="Periodo" :options="[
                'hoy' => 'Hoy',
                'ayer' => 'Ayer',
                'esta_semana' => 'Esta semana',
                'este_mes' => 'Este mes',
                'personalizado' => 'Personalizado',
            ]" :value="request('periodo', 'hoy')" />
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

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Producciones en curso" :value="$dashboard->kpis['enCurso']" accent="info" />
        <x-ui.stat-card label="Lotes producidos" :value="$dashboard->kpis['lotesProducidos']" hint="Periodo seleccionado" />
        <x-ui.stat-card label="Litros utilizados" :value="number_format($dashboard->kpis['litrosUtilizados'], 2).' L'" hint="Periodo seleccionado" />
        <x-ui.stat-card label="Productos producidos" :value="number_format($dashboard->kpis['productosProducidos'], 2)" hint="Periodo seleccionado" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card title="Producción actual">
            <x-slot:actions>
                <x-ui.button :href="route('planta.produccion.index').'?estado=EN_PROCESO'" variant="ghost" size="sm" as="a">Ver todas →</x-ui.button>
            </x-slot:actions>

            @if (count($dashboard->produccionActual) === 0)
                <x-ui.empty-state title="No hay producciones en curso" :icon="false" />
            @else
                <ul class="divide-y divide-text-muted/10">
                    @foreach ($dashboard->produccionActual as $lote)
                        <li class="flex items-center justify-between py-2 text-sm">
                            <a class="font-mono text-xs text-text hover:text-primary hover:underline" href="{{ route('planta.produccion.show', $lote->id()) }}">{{ $lote->codigo() }}</a>
                            <span class="text-text-muted">{{ $lote->fechaHoraInicio()->format('H:i') }}</span>
                            <span class="font-medium text-text">{{ number_format($dashboard->litrosPorLote[$lote->id()] ?? 0, 2) }} L</span>
                            <x-ui.badge :status="$lote->estado()" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Alertas de insumos">
            @if (count($dashboard->insumosBajoMinimo) === 0)
                <div class="rounded-lg bg-success/10 p-4 text-sm text-success">
                    🟢 No hay insumos que requieran atención.
                </div>
            @else
                <ul class="divide-y divide-text-muted/10">
                    @foreach ($dashboard->insumosBajoMinimo as $insumo)
                        <li class="flex items-center justify-between py-2 text-sm">
                            <a class="text-text hover:text-primary hover:underline" href="{{ route('planta.insumos.show', $insumo->id()) }}">
                                {{ $insumo->stockActual() <= 0 ? '🔴' : '🟡' }} {{ $insumo->nombre() }}
                            </a>
                            <span class="text-text-muted">{{ number_format($insumo->stockActual(), 2) }} / mín. {{ number_format($insumo->stockMinimo(), 2) }} {{ $insumo->unidadBase() }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-3">
                    <x-ui.button :href="route('planta.insumos.index')" variant="ghost" size="sm" as="a">Ver inventario →</x-ui.button>
                </div>
            @endif
        </x-ui.card>
    </div>

    <x-ui.card title="Producción reciente">
        <x-slot:actions>
            <x-ui.button :href="route('planta.produccion.index')" variant="ghost" size="sm" as="a">Ver todos →</x-ui.button>
        </x-slot:actions>

        @if (count($dashboard->produccionReciente) === 0)
            <x-ui.empty-state title="No hay registros de producción para el periodo seleccionado" description="Registra un lote para comenzar." :icon="false" />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Código</th>
                        <th class="py-2 pr-4">Inicio</th>
                        <th class="py-2 pr-4">Litros</th>
                        <th class="py-2 pr-4">Estado</th>
                        <th class="py-2 pl-4 text-right">Ver</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dashboard->produccionReciente as $lote)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-2 pr-4 font-mono text-xs text-text">{{ $lote->codigo() }}</td>
                            <td class="py-2 pr-4 text-text-muted">{{ $lote->fechaHoraInicio()->format('d/m/Y H:i') }}</td>
                            <td class="py-2 pr-4 text-text">{{ number_format($dashboard->litrosPorLote[$lote->id()] ?? 0, 2) }} L</td>
                            <td class="py-2 pr-4"><x-ui.badge compact :status="$lote->estado()" /></td>
                            <td class="py-2 pl-4 text-right"><a class="text-primary hover:underline" href="{{ route('planta.produccion.show', $lote->id()) }}">Ver</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card title="Estado de inventario">
            <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <dt class="text-xs uppercase tracking-wide text-text-muted">Total</dt>
                    <dd class="mt-1 text-lg font-semibold text-text">{{ $dashboard->inventario['total'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-text-muted">Suficiente</dt>
                    <dd class="mt-1 text-lg font-semibold text-success">{{ $dashboard->inventario['suficiente'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-text-muted">Bajo</dt>
                    <dd class="mt-1 text-lg font-semibold text-warning">{{ $dashboard->inventario['bajo'] }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-text-muted">Sin stock</dt>
                    <dd class="mt-1 text-lg font-semibold text-danger">{{ $dashboard->inventario['sinStock'] }}</dd>
                </div>
            </dl>
        </x-ui.card>

        <x-ui.card title="Productos disponibles">
            <x-slot:actions>
                <x-ui.button :href="route('planta.productos.index')" variant="ghost" size="sm" as="a">Ver productos →</x-ui.button>
            </x-slot:actions>

            @if (count($dashboard->productosDisponibles) === 0)
                <x-ui.empty-state title="No hay productos disponibles en stock actualmente" :icon="false" />
            @else
                <ul class="divide-y divide-text-muted/10">
                    @foreach ($dashboard->productosDisponibles as $item)
                        <li class="flex items-center justify-between py-2 text-sm">
                            <span class="text-text">{{ $item['producto'] }}</span>
                            <span class="font-medium text-text">{{ number_format($item['stock'], 2) }} {{ $item['unidad'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>

    <x-ui.card title="Notificaciones recientes">
        <x-slot:actions>
            <x-ui.button :href="route('notificaciones.index')" variant="ghost" size="sm" as="a">Ver todas →</x-ui.button>
        </x-slot:actions>

        @if (count($dashboard->notificacionesRecientes) === 0)
            <x-ui.empty-state title="No tienes notificaciones" :icon="false" />
        @else
            <ul class="divide-y divide-text-muted/10">
                @foreach ($dashboard->notificacionesRecientes as $notificacion)
                    <li class="py-2 text-sm">
                        <p class="font-medium text-text">{{ $notificacion->titulo() }}</p>
                        <p class="text-xs text-text-muted">{{ $notificacion->mensaje() }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
@endsection
