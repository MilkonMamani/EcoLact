@extends('layouts.acopiador')

@section('title', 'Dashboard')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-semibold text-text">Dashboard</h1>
            <p class="text-sm text-text-muted">Aquí tienes el resumen de tu actividad.</p>
        </div>
        <x-ui.button :href="route('acopiador.entregas.create')" as="a">+ Registrar entrega</x-ui.button>
    </div>

    <form method="GET" class="flex flex-wrap items-end gap-3">
        <div class="w-48">
            <x-forms.select name="periodo" label="Periodo" :options="[
                'hoy' => 'Hoy',
                'ayer' => 'Ayer',
                'esta_semana' => 'Esta semana',
                'semana_anterior' => 'Semana anterior',
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
        <x-ui.stat-card label="Entregas" :value="$dashboard->kpis['entregas']" hint="Entregas del periodo" />
        <x-ui.stat-card label="Litros" :value="number_format($dashboard->kpis['litros'], 2).' L'" hint="Recolectados" />
        <x-ui.stat-card label="Proveedores" :value="$dashboard->kpis['proveedoresAtendidos']" hint="Atendidos" />
        <x-ui.stat-card label="Pendientes" :value="$dashboard->kpis['pendientesAnalisis']" hint="De análisis" accent="warning" />
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card title="Últimas entregas">
            <x-slot:actions>
                <x-ui.button :href="route('acopiador.entregas.index')" variant="ghost" size="sm" as="a">Ver todas →</x-ui.button>
            </x-slot:actions>

            @if (count($dashboard->ultimasEntregas) === 0)
                <x-ui.empty-state title="Aún no registras entregas" description="Comienza registrando la primera entrega." :icon="false" />
            @else
                <ul class="divide-y divide-text-muted/10">
                    @foreach ($dashboard->ultimasEntregas as $entrega)
                        <li class="flex items-center justify-between py-2 text-sm">
                            <span class="text-text-muted">{{ $entrega->fechaHora()->format('H:i') }}</span>
                            <span class="font-medium text-text">{{ number_format($entrega->litros(), 2) }} L</span>
                            @if ($dashboard->resultadosPorEntrega[$entrega->id()] ?? null)
                                <x-ui.badge :status="$dashboard->resultadosPorEntrega[$entrega->id()]" />
                            @else
                                <x-ui.badge :status="$entrega->estado()->value" />
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Pendientes de análisis">
            @if ($dashboard->kpis['pendientesAnalisis'] === 0)
                <div class="rounded-lg bg-success/10 p-4 text-sm text-success">
                    ✓ No tienes entregas pendientes de análisis.
                </div>
            @else
                <div class="rounded-lg bg-warning/10 p-4 text-sm text-warning">
                    🧪 {{ $dashboard->kpis['pendientesAnalisis'] }} entregas todavía no cuentan con resultado de calidad.
                </div>
                <div class="mt-4">
                    <x-ui.button :href="route('acopiador.seguimiento-calidad.index')" variant="ghost" size="sm" as="a">Ver seguimiento →</x-ui.button>
                </div>
            @endif
        </x-ui.card>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card title="Proveedores atendidos">
            <x-slot:actions>
                <x-ui.button :href="route('acopiador.proveedores.index')" variant="ghost" size="sm" as="a">Ver proveedores →</x-ui.button>
            </x-slot:actions>

            @if (count($dashboard->proveedoresAtendidos) === 0)
                <x-ui.empty-state title="Aún no atendiste proveedores en este periodo" :icon="false" />
            @else
                <ul class="divide-y divide-text-muted/10">
                    @foreach ($dashboard->proveedoresAtendidos as $item)
                        <li class="flex items-center justify-between py-2 text-sm">
                            <a class="text-text hover:text-primary hover:underline" href="{{ route('acopiador.proveedores.show', $item['proveedorId']) }}">{{ $item['nombre'] }}</a>
                            <span class="text-text-muted">{{ $item['entregas'] }} {{ \Illuminate\Support\Str::plural('entrega', $item['entregas']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Comunicados recientes">
            <x-slot:actions>
                <x-ui.button :href="route('acopiador.comunicados.index')" variant="ghost" size="sm" as="a">Ver todos →</x-ui.button>
            </x-slot:actions>

            @if (count($dashboard->comunicadosRecientes) === 0)
                <x-ui.empty-state title="No hay comunicados" :icon="false" />
            @else
                <ul class="divide-y divide-text-muted/10">
                    @foreach ($dashboard->comunicadosRecientes as $comunicado)
                        <li class="py-2 text-sm">
                            <a class="font-medium text-text hover:text-primary hover:underline" href="{{ route('acopiador.comunicados.show', $comunicado->id()) }}">{{ $comunicado->titulo() }}</a>
                            <p class="text-xs text-text-muted">{{ $comunicado->fechaPublicacion()->format('d/m/Y') }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
@endsection
