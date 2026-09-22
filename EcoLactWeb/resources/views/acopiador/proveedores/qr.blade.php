@extends('layouts.acopiador')

@section('title', 'QR de ' . $proveedor->nombreCompleto())

@section('content')
    <x-ui.button :href="route('acopiador.proveedores.show', $proveedor->id())" variant="ghost" size="sm" as="a">&larr; Volver al proveedor</x-ui.button>

    <x-ui.card title="Código QR del proveedor" subtitle="Úsalo para identificar al proveedor al registrar una entrega.">
        <div class="flex flex-col items-center gap-4 py-6 text-center">
            <div class="flex min-h-60 min-w-60 items-center justify-center rounded-2xl border-2 border-primary/40 bg-primary-soft p-3">
                <canvas data-qr-code="{{ base64_encode($proveedor->qrToken()) }}" class="rounded-lg bg-white p-2" aria-label="Código QR de {{ $proveedor->nombreCompleto() }}"></canvas>
            </div>
            <p class="text-sm font-medium text-text">Código QR disponible</p>

            <dl class="grid w-full max-w-sm gap-2 text-left text-sm">
                <div class="flex justify-between border-b border-text-muted/10 py-1.5">
                    <dt class="text-text-muted">Proveedor</dt>
                    <dd class="font-medium text-text">{{ $proveedor->nombreCompleto() }}</dd>
                </div>
                <div class="flex justify-between py-1.5">
                    <dt class="text-text-muted">Zona</dt>
                    <dd class="text-text">{{ $zona?->nombre ?? 'Zona #'.$proveedor->zonaId() }}</dd>
                </div>
            </dl>

            <p class="max-w-sm text-xs text-text-muted">Al escanearlo se identificará a {{ $proveedor->nombreCompleto() }}.</p>
        </div>
    </x-ui.card>
@endsection
