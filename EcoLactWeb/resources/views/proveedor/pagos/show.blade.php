@extends('layouts.proveedor')

@section('title', 'Pago #' . $pago->id())

@section('content')
    <x-ui.button :href="route('proveedor.pagos.index')" variant="ghost" size="sm" as="a">&larr; Volver a mis pagos</x-ui.button>

    <x-ui.card :title="'Pago #' . $pago->id()" subtitle="Detalle del pago recibido.">
        <x-slot:actions>
            <x-ui.badge :status="$pago->estado()->value" />
        </x-slot:actions>

        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Liquidación</dt>
                <dd class="mt-1 text-sm text-text">
                    <a class="text-primary hover:underline" href="{{ route('proveedor.liquidaciones.show', $pago->liquidacionId()) }}">Liquidación #{{ $pago->liquidacionId() }}</a>
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Monto</dt>
                <dd class="mt-1 text-sm text-text">S/ {{ number_format($pago->monto(), 2) }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Fecha de pago</dt>
                <dd class="mt-1 text-sm text-text">{{ $pago->fechaPago()->format('d/m/Y') }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Método</dt>
                <dd class="mt-1 text-sm text-text">Efectivo (sobre cerrado)</dd>
            </div>
        </dl>
    </x-ui.card>
@endsection
