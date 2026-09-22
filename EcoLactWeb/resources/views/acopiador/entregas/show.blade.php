@extends('layouts.acopiador')

@section('title', 'Entrega #' . $entrega->id())

@section('content')
    <x-ui.button :href="route('acopiador.entregas.index')" variant="ghost" size="sm" as="a">&larr; Volver a entregas</x-ui.button>

    <x-ui.card :title="'Entrega #' . $entrega->id()" subtitle="Detalle de la entrega de leche">
        <x-slot:actions>
            <x-ui.badge :status="$entrega->estado()->value" />
        </x-slot:actions>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Fecha</dt>
                <dd class="mt-1 text-sm text-text">{{ $entrega->fechaHora()->format('d/m/Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Hora</dt>
                <dd class="mt-1 text-sm text-text">{{ $entrega->fechaHora()->format('H:i') }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Litros entregados</dt>
                <dd class="mt-1 text-sm text-text">{{ number_format($entrega->litros(), 2) }} L</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Método de identificación</dt>
                <dd class="mt-1 text-sm text-text">{{ $entrega->metodoIdentificacion() === 'QR' ? '📱 QR' : '🪪 DNI' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs uppercase tracking-wide text-text-muted">Observación</dt>
                <dd class="mt-1 text-sm text-text">{{ $entrega->observacion() ?? 'Sin observaciones.' }}</dd>
            </div>
        </dl>
    </x-ui.card>

    <x-ui.card title="Proveedor">
        <x-slot:actions>
            <x-ui.badge :status="$proveedor->estado" />
        </x-slot:actions>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Nombre</dt>
                <dd class="mt-1 text-sm text-text">{{ trim($proveedor->nombres.' '.$proveedor->apellidos) }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Código</dt>
                <dd class="mt-1 text-sm text-text">PRV-{{ str_pad((string) $proveedor->id, 6, '0', STR_PAD_LEFT) }}</dd>
            </div>
        </dl>

        <div class="mt-4">
            <x-ui.button :href="route('acopiador.proveedores.show', $proveedor->id)" variant="ghost" size="sm" as="a">Ver proveedor →</x-ui.button>
        </div>
    </x-ui.card>

    <x-ui.card title="Resultado de calidad">
        @if ($analisis === null)
            <div class="rounded-lg bg-warning/10 p-4 text-sm text-warning">
                🕐 Esta entrega todavía no tiene un análisis de calidad registrado.
            </div>
        @else
            <x-slot:actions>
                @if ($analisis->resultado())
                    <x-ui.badge :status="$analisis->resultado()->value" />
                @else
                    <x-ui.badge status="PENDIENTE_ANALISIS">Pendiente de confirmación</x-ui.badge>
                @endif
            </x-slot:actions>

            <p class="text-sm text-text-muted">Fecha del análisis: {{ $analisis->fechaAnalisis()->format('d/m/Y') }}</p>

            @if (in_array($analisis->resultado()?->value, ['OBSERVADA', 'RECHAZADA'], true))
                <p class="mt-2 text-sm text-text">Observación: {{ $analisis->observacion() ?? 'Sin observación registrada.' }}</p>
            @endif

            <div class="mt-4">
                <x-ui.button :href="route('acopiador.seguimiento-calidad.show', $entrega->id())" variant="ghost" size="sm" as="a">Ver seguimiento de calidad →</x-ui.button>
            </div>
        @endif
    </x-ui.card>
@endsection
