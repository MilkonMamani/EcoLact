@extends('layouts.ventas-panel')

@section('title', 'Dashboard')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-text">Dashboard</h1>
            <p class="text-sm text-text-muted">Aquí tienes un resumen de la actividad comercial.</p>
        </div>
        <x-ui.button :href="route('ventas.ventas.create')" as="a">Registrar venta</x-ui.button>
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3">
        <div class="w-48">
            <x-forms.select name="periodo" label="Periodo" :options="[
                'hoy' => 'Hoy',
                'ayer' => 'Ayer',
                'esta_semana' => 'Esta semana',
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
        <x-ui.button type="submit" variant="secondary" size="sm">Filtrar</x-ui.button>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Ventas" :value="'S/ '.number_format($dashboard->kpis['totalVendido'], 2)" accent="accent" hint="Total vendido" />
        <x-ui.stat-card label="Operaciones" :value="$dashboard->kpis['ventas']" hint="Ventas registradas" />
        <x-ui.stat-card label="Productos vendidos" :value="number_format($dashboard->kpis['productosVendidos'], 2)" hint="Unidades vendidas" />
        <x-ui.stat-card label="Ticket promedio" :value="$dashboard->kpis['ticketPromedio'] !== null ? 'S/ '.number_format($dashboard->kpis['ticketPromedio'], 2) : '—'" hint="Promedio por venta" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card title="Ventas recientes">
            <x-slot:actions>
                <x-ui.button :href="route('ventas.ventas.index')" variant="ghost" size="sm" as="a">Ver todas →</x-ui.button>
            </x-slot:actions>

            @if (count($dashboard->ventasRecientes) === 0)
                <x-ui.empty-state title="No hay ventas registradas en este periodo" :icon="false" />
            @else
                <ul class="divide-y divide-text-muted/10">
                    @foreach ($dashboard->ventasRecientes as $venta)
                        <li class="flex items-center justify-between py-2 text-sm">
                            <a class="text-text hover:text-primary hover:underline" href="{{ route('ventas.ventas.show', $venta->id()) }}">#{{ $venta->id() }}</a>
                            <span class="text-text-muted">{{ $venta->fecha()->format('d/m H:i') }}</span>
                            <span class="font-medium text-text">S/ {{ number_format($venta->importeTotal(), 2) }}</span>
                            <x-ui.badge :status="$venta->estado()" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Stock crítico">
            <x-slot:actions>
                <x-ui.button :href="route('ventas.productos.index')" variant="ghost" size="sm" as="a">Ver inventario →</x-ui.button>
            </x-slot:actions>

            @if (count($dashboard->stockCritico) === 0)
                <div class="rounded-lg bg-success/10 p-4 text-sm text-success">
                    ✓ No hay productos con stock crítico.
                </div>
            @else
                <ul class="divide-y divide-text-muted/10">
                    @foreach ($dashboard->stockCritico as $item)
                        <li class="flex items-center justify-between py-2 text-sm">
                            <span class="text-text">🔴 {{ $item['producto'] }}</span>
                            <span class="text-text-muted">{{ number_format($item['stock'], 2) }} {{ $item['unidad'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card title="Productos más vendidos">
            @if (count($dashboard->masVendidos) === 0)
                <x-ui.empty-state title="Aún no hay ventas en este periodo" :icon="false" />
            @else
                <ul class="divide-y divide-text-muted/10">
                    @foreach ($dashboard->masVendidos as $i => $item)
                        <li class="flex items-center justify-between py-2 text-sm">
                            <span class="text-text">{{ $i + 1 }}. {{ $item['producto'] }}</span>
                            <span class="font-medium text-text">{{ number_format($item['cantidad'], 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

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
    </div>
@endsection
