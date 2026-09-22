@extends('layouts.proveedor')

@section('title', 'Mi QR')

@section('content')
    <x-ui.card title="Mi código QR" subtitle="Preséntalo al acopiador para registrar tu entrega.">
        <div class="flex flex-col items-center gap-4 py-6 text-center">
            <div class="flex min-h-60 min-w-60 items-center justify-center rounded-2xl border-2 border-primary/40 bg-primary-soft p-3">
                <canvas data-qr-code="{{ base64_encode($proveedor->qrToken()) }}" class="rounded-lg bg-white p-2" aria-label="Código QR de {{ $proveedor->nombreCompleto() }}"></canvas>
            </div>
            <p class="text-sm font-medium text-text">Código QR disponible</p>

            <div class="w-full max-w-sm rounded-lg bg-background px-4 py-3 text-left">
                <p class="text-xs uppercase tracking-wide text-text-muted">Token</p>
                <p class="mt-1 break-all font-mono text-sm text-text">{{ $proveedor->qrToken() }}</p>
            </div>

            <dl class="grid w-full max-w-sm gap-2 text-left text-sm">
                <div class="flex justify-between border-b border-text-muted/10 py-1.5">
                    <dt class="text-text-muted">Proveedor</dt>
                    <dd class="font-medium text-text">{{ $proveedor->nombreCompleto() }}</dd>
                </div>
                <div class="flex justify-between border-b border-text-muted/10 py-1.5">
                    <dt class="text-text-muted">DNI</dt>
                    <dd class="text-text">{{ $proveedor->dni() }}</dd>
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
