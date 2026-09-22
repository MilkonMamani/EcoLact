@extends('layouts.proveedor')

@section('title', 'Dashboard')

@section('content')
    <div>
        <h1 class="text-lg font-semibold text-text">Dashboard</h1>
        <p class="text-sm text-text-muted">Aquí tienes un resumen de tu actividad.</p>
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3">
        <div class="w-48">
            <x-forms.select name="periodo" label="Periodo" :options="[
                'esta_semana' => 'Esta semana',
                'semana_anterior' => 'Semana anterior',
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

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card label="Entregas realizadas" :value="$dashboard->kpis['entregas']" />
        <x-ui.stat-card label="Litros entregados" :value="number_format($dashboard->kpis['litros'], 2).' L'" />
        <x-ui.stat-card label="Calidad" :value="$dashboard->ultimoAnalisis?->resultado()?->value ?? 'Sin análisis'" hint="Último resultado" />
        <x-ui.stat-card label="Por recibir (estimado)" :value="'S/ '.number_format($dashboard->porCobrar, 2)" hint="Incluye entregas aún no analizadas" accent="warning" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card title="Mi última liquidación">
            @if ($dashboard->ultimaLiquidacion === null)
                <x-ui.empty-state title="Aún no tienes liquidaciones" :icon="false" />
            @else
                @if ($dashboard->ultimaLiquidacionPeriodo)
                    <p class="text-sm text-text-muted">Periodo {{ $dashboard->ultimaLiquidacionPeriodo->fechaInicio()->format('d/m/Y') }} — {{ $dashboard->ultimaLiquidacionPeriodo->fechaFin()->format('d/m/Y') }}</p>
                @endif
                <p class="mt-2 text-2xl font-semibold text-text">S/ {{ number_format($dashboard->ultimaLiquidacion->totalPagar(), 2) }}</p>
                <div class="mt-1"><x-ui.badge :status="$dashboard->ultimaLiquidacion->estado()->value" /></div>
                <div class="mt-4">
                    <x-ui.button :href="route('proveedor.liquidaciones.show', $dashboard->ultimaLiquidacion->id())" variant="ghost" size="sm" as="a">Ver liquidación →</x-ui.button>
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Último análisis de calidad">
            @if ($dashboard->ultimoAnalisis === null)
                <x-ui.empty-state title="Aún no tienes análisis registrados" :icon="false" />
            @else
                <p class="text-sm text-text-muted">Entrega #{{ $dashboard->ultimoAnalisis->entregaId() }}</p>
                <p class="mt-1 text-sm text-text-muted">{{ $dashboard->ultimoAnalisis->fechaAnalisis()->format('d/m/Y') }}</p>
                <div class="mt-2">
                    @if ($dashboard->ultimoAnalisis->resultado())
                        <x-ui.badge :status="$dashboard->ultimoAnalisis->resultado()->value" class="px-4 py-2 text-base" />
                    @else
                        <span class="text-sm text-text-muted">Pendiente de confirmación</span>
                    @endif
                </div>
                <div class="mt-4">
                    <x-ui.button :href="route('proveedor.analisis.show', $dashboard->ultimoAnalisis->id())" variant="ghost" size="sm" as="a">Ver análisis →</x-ui.button>
                </div>
            @endif
        </x-ui.card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card title="Últimas entregas">
            <x-slot:actions>
                <x-ui.button :href="route('proveedor.entregas.index')" variant="ghost" size="sm" as="a">Ver todas →</x-ui.button>
            </x-slot:actions>

            @if (count($dashboard->ultimasEntregas) === 0)
                <x-ui.empty-state title="Aún no tienes entregas registradas" :icon="false" />
            @else
                <ul class="divide-y divide-text-muted/10">
                    @foreach ($dashboard->ultimasEntregas as $entrega)
                        @php($resultado = $dashboard->resultadosPorEntrega[$entrega->id()] ?? null)
                        <li class="flex items-center justify-between py-2 text-sm">
                            <span class="text-text-muted">{{ $entrega->fechaHora()->format('d/m/Y') }}</span>
                            <span class="font-medium text-text">{{ number_format($resultado === 'RECHAZADA' ? 0.0 : $entrega->litros(), 2) }} L</span>
                            <x-ui.badge :status="$resultado ?? $entrega->estado()->value" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Mis compras recientes">
            <x-slot:actions>
                <x-ui.button :href="route('proveedor.compras.index')" variant="ghost" size="sm" as="a">Ver compras →</x-ui.button>
            </x-slot:actions>

            @if (count($dashboard->comprasRecientes) === 0)
                <x-ui.empty-state title="Aún no tienes compras registradas" :icon="false" />
            @else
                <ul class="divide-y divide-text-muted/10">
                    @foreach ($dashboard->comprasRecientes as $compra)
                        <li class="flex items-center justify-between py-2 text-sm">
                            <span class="text-text-muted">{{ $compra->fecha()->format('d/m/Y') }}</span>
                            <span class="font-medium text-text">S/ {{ number_format($compra->importeTotal(), 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>

    <x-ui.card title="Comunicados recientes">
        <x-slot:actions>
            <x-ui.button :href="route('proveedor.comunicados.index')" variant="ghost" size="sm" as="a">Ver todos →</x-ui.button>
        </x-slot:actions>

        @if (count($dashboard->comunicadosRecientes) === 0)
            <x-ui.empty-state title="No hay comunicados" :icon="false" />
        @else
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ($dashboard->comunicadosRecientes as $comunicado)
                    <a href="{{ route('proveedor.comunicados.show', $comunicado->id()) }}" class="block rounded-xl border border-text-muted/10 bg-surface p-3 shadow-sm transition-shadow hover:shadow-md">
                        <x-ui.badge variant="info">{{ $comunicado->tipo() }}</x-ui.badge>
                        <p class="mt-2 text-sm font-medium text-text">{{ $comunicado->titulo() }}</p>
                        <p class="mt-1 text-xs text-text-muted">{{ $comunicado->fechaPublicacion()->format('d/m/Y') }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </x-ui.card>
@endsection
