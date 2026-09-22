@extends('layouts.acopiador')

@section('title', 'Seguimiento · Entrega #' . $entrega->id)

@section('content')
    <x-ui.button :href="route('acopiador.seguimiento-calidad.index')" variant="ghost" size="sm" as="a">&larr; Volver</x-ui.button>

    <x-ui.card :title="'Entrega #' . $entrega->id" subtitle="Seguimiento de calidad">
        <x-slot:actions>
            <x-ui.badge :status="$entrega->estado" />
        </x-slot:actions>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Fecha</dt>
                <dd class="mt-1 text-sm text-text">{{ \Illuminate\Support\Carbon::parse($entrega->fecha_hora)->format('d/m/Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Hora</dt>
                <dd class="mt-1 text-sm text-text">{{ \Illuminate\Support\Carbon::parse($entrega->fecha_hora)->format('H:i') }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Litros</dt>
                <dd class="mt-1 text-sm text-text">{{ number_format((float) $entrega->litros, 2) }} L</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Identificación</dt>
                <dd class="mt-1 text-sm text-text">{{ $entrega->metodo_identificacion === 'QR' ? '📱 QR' : '🪪 DNI' }}</dd>
            </div>
        </dl>

        <div class="mt-4">
            <x-ui.button :href="route('acopiador.entregas.show', $entrega->id)" variant="ghost" size="sm" as="a">Ver entrega →</x-ui.button>
        </div>
    </x-ui.card>

    <x-ui.card title="Proveedor">
        <p class="text-sm text-text">👤 {{ trim($entrega->proveedor->nombres.' '.$entrega->proveedor->apellidos) }}</p>
        <p class="mt-1 text-sm text-text-muted">DNI: {{ substr($entrega->proveedor->dni, 0, 2) }}****{{ substr($entrega->proveedor->dni, -2) }}</p>
        <div class="mt-4">
            <x-ui.button :href="route('acopiador.proveedores.show', $entrega->proveedor->id)" variant="ghost" size="sm" as="a">Ver proveedor →</x-ui.button>
        </div>
    </x-ui.card>

    <x-ui.card title="Resultado de calidad">
        @if ($analisis === null)
            <div class="rounded-lg bg-warning/10 p-4 text-sm text-warning">
                🟠 Pendiente de análisis. Esta entrega aún no cuenta con un resultado de calidad.
            </div>
        @else
            <x-slot:actions>
                @if ($analisis->resultado())
                    <x-ui.badge :status="$analisis->resultado()->value" />
                @else
                    <x-ui.badge status="PENDIENTE_ANALISIS">Pendiente de confirmación</x-ui.badge>
                @endif
            </x-slot:actions>

            @if ($analisis->resultado()?->value === 'ACEPTADA')
                <p class="text-sm text-text-muted">La entrega fue analizada y obtuvo un resultado aceptado.</p>
            @elseif ($analisis->resultado()?->value === 'OBSERVADA')
                <p class="text-sm text-text-muted">La entrega presenta observaciones de calidad.</p>
            @elseif ($analisis->resultado()?->value === 'RECHAZADA')
                <p class="text-sm text-text-muted">La entrega obtuvo un resultado rechazado.</p>
            @endif

            @if ($analisis->observacion())
                <p class="mt-2 text-sm text-text">Observación: {{ $analisis->observacion() }}</p>
            @endif
        @endif
    </x-ui.card>
@endsection
