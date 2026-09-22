@extends('layouts.acopiador')

@section('title', $proveedor->nombreCompleto())

@section('content')
    <x-ui.button :href="route('acopiador.proveedores.index')" variant="ghost" size="sm" as="a">&larr; Volver a proveedores</x-ui.button>

    <x-ui.card :title="$proveedor->nombreCompleto()" :subtitle="'PRV-'.str_pad((string) $proveedor->id(), 6, '0', STR_PAD_LEFT)">
        <x-slot:actions>
            <x-ui.badge :status="$proveedor->estado()->value" />
        </x-slot:actions>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">DNI</dt>
                <dd class="mt-1 text-sm text-text">{{ substr($proveedor->dni(), 0, 2) }}****{{ substr($proveedor->dni(), -2) }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Teléfono</dt>
                <dd class="mt-1 text-sm text-text">{{ $proveedor->telefono() ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Fecha de registro</dt>
                <dd class="mt-1 text-sm text-text">{{ $proveedor->fechaRegistro()->format('d/m/Y') }}</dd>
            </div>
        </dl>

        @if ($proveedor->estado()->value !== 'ACTIVO')
            <div class="mt-4 rounded-lg bg-text-muted/10 p-3 text-sm text-text-muted">
                ⚪ Este proveedor actualmente no se encuentra activo.
            </div>
        @endif
    </x-ui.card>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card title="Zona">
            <p class="text-sm text-text">📍 Zona #{{ $proveedor->zonaId() }}</p>
            <p class="mt-1 text-xs text-text-muted">Estado de asignación: Actual</p>
        </x-ui.card>

        <x-ui.card title="Identificación">
            <p class="text-sm text-text-muted">📱 QR del proveedor</p>
            <div class="mt-2">
                <x-ui.button :href="route('acopiador.proveedores.qr', $proveedor->id())" variant="ghost" size="sm" as="a">Ver QR →</x-ui.button>
            </div>
        </x-ui.card>
    </div>

    <x-ui.card title="Resumen de entregas">
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Entregas totales</dt>
                <dd class="mt-1 text-lg font-semibold text-text">{{ $resumen['entregasTotales'] }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Litros entregados</dt>
                <dd class="mt-1 text-lg font-semibold text-text">{{ number_format($resumen['litrosEntregados'], 2) }} L</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Última entrega</dt>
                <dd class="mt-1 text-sm text-text">{{ $resumen['ultimaEntrega'] ? \Illuminate\Support\Carbon::parse($resumen['ultimaEntrega'])->format('d/m/Y') : 'Sin entregas' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Pendientes de análisis</dt>
                <dd class="mt-1 text-lg font-semibold text-text">{{ $resumen['pendientesAnalisis'] }}</dd>
            </div>
        </dl>
    </x-ui.card>

    <x-ui.card title="Actividad reciente" subtitle="Últimas entregas registradas">
        @if (count($entregasRecientes) === 0)
            <x-ui.empty-state title="Aún no hay entregas registradas" :icon="false" />
        @else
            <ul class="divide-y divide-text-muted/10">
                @foreach ($entregasRecientes as $entrega)
                    <li class="flex items-center justify-between py-2 text-sm">
                        <span class="text-text-muted">{{ \Illuminate\Support\Carbon::parse($entrega->fecha_hora)->format('d/m/Y H:i') }}</span>
                        <span class="font-medium text-text">{{ number_format((float) $entrega->litros, 2) }} L</span>
                        <x-ui.badge :status="$entrega->estado" />
                        <x-ui.button :href="route('acopiador.entregas.show', $entrega->id)" variant="ghost" size="sm" as="a">Ver entrega →</x-ui.button>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card title="Calidad">
            @if ($ultimoAnalisis === null)
                <p class="text-sm text-text-muted">🕐 Sin análisis registrados. Todavía no existen resultados de calidad para mostrar.</p>
            @else
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-text-muted">Último resultado</p>
                        <div class="mt-1">
                            <x-ui.badge :status="$ultimoAnalisis->resultado()?->value ?? 'PENDIENTE_ANALISIS'" />
                        </div>
                        <p class="mt-1 text-xs text-text-muted">{{ $ultimoAnalisis->fechaAnalisis()->format('d/m/Y') }}</p>
                    </div>
                </div>
                <div class="mt-4">
                    <x-ui.button :href="route('acopiador.seguimiento-calidad.show', $ultimoAnalisis->entregaId())" variant="ghost" size="sm" as="a">Ver seguimiento de calidad →</x-ui.button>
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Seguimiento">
            <p class="text-xs uppercase tracking-wide text-text-muted">Advertencias registradas</p>
            <p class="mt-1 text-lg font-semibold text-text">{{ $proveedor->advertenciasCount() }}</p>
        </x-ui.card>
    </div>

    @if ($proveedor->estado()->value === 'ACTIVO')
        <x-ui.button :href="route('acopiador.entregas.create', ['dni' => $proveedor->dni()])" as="a">+ Registrar entrega</x-ui.button>
    @endif
@endsection
