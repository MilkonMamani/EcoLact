@extends('layouts.proveedor')

@section('title', 'Entrega #' . $entrega->id())

@section('content')
    <x-ui.button :href="route('proveedor.entregas.index')" variant="ghost" size="sm" as="a">&larr; Volver a mis entregas</x-ui.button>

    <x-ui.card :title="'Entrega #' . $entrega->id()" subtitle="Detalle de tu entrega de leche">
        <x-slot:actions>
            <x-ui.badge :status="$entrega->estado()->value" />
        </x-slot:actions>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Fecha y hora</dt>
                <dd class="mt-1 text-sm text-text">{{ $entrega->fechaHora()->format('d/m/Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Litros</dt>
                <dd class="mt-1 text-sm text-text">{{ number_format($entrega->litros(), 2) }} L</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Método de identificación</dt>
                <dd class="mt-1 text-sm text-text">{{ $entrega->metodoIdentificacion() }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs uppercase tracking-wide text-text-muted">Observación</dt>
                <dd class="mt-1 text-sm text-text">{{ $entrega->observacion() ?? 'Sin observaciones.' }}</dd>
            </div>
        </dl>
    </x-ui.card>

    <x-ui.card title="Resultado de calidad">
        @if ($analisis === null)
            <div class="rounded-lg bg-text-muted/5 p-4 text-sm text-text-muted">
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

            @if ($analisis->resultado()?->value === 'RECHAZADA')
                <div class="rounded-lg bg-danger/10 p-4 text-sm text-danger">
                    <p class="font-semibold">⚠️ Esta entrega fue rechazada por Calidad.</p>
                    <p class="mt-1">Litros entregados: {{ number_format($entrega->litros(), 2) }} L · Litros pagables: 0.00 L</p>
                </div>
            @elseif ($analisis->resultado()?->value === 'OBSERVADA')
                <p class="text-sm text-text-muted">Fecha del análisis: {{ $analisis->fechaAnalisis()->format('d/m/Y') }}</p>
                <p class="mt-2 text-sm text-text">Observación: {{ $analisis->observacion() ?? 'Sin observación registrada.' }}</p>
            @elseif ($analisis->resultado()?->value === 'ACEPTADA')
                <p class="text-sm text-text-muted">Fecha del análisis: {{ $analisis->fechaAnalisis()->format('d/m/Y') }}</p>
            @else
                <p class="text-sm text-text-muted">El análisis está en borrador, todavía sin resultado confirmado.</p>
            @endif

            <div class="mt-4">
                <x-ui.button :href="route('proveedor.analisis.show', $analisis->id())" variant="ghost" size="sm" as="a">Ver análisis completo →</x-ui.button>
            </div>
        @endif
    </x-ui.card>
@endsection
