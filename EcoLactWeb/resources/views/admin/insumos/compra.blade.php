{{--
    Detalle de una compra de insumos (histórico): comprobante, quién la confirmó y cada línea con la
    presentación usada al comprar. No se recalcula con las presentaciones de hoy.
    Variables: $detalle (ObtenerDetalleCompraInsumo), $imagenUrl (string|null).
--}}
@extends('layouts.admin')

@php
    $compra = $detalle['compra'];
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4), '0'), '.');
    $rotulo = 'text-xs font-medium uppercase tracking-wide text-text-muted';
@endphp

@section('title', 'Compra #'.$compra->id())

@section('content')
    <x-ui.button :href="route('admin.insumos.index')" variant="ghost" size="sm" as="a">
        <x-ui.icon name="arrow-left" class="h-4 w-4" /> Volver a insumos
    </x-ui.button>

    <article class="overflow-hidden rounded-2xl border border-text-muted/10 bg-surface">
        <header class="p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-bold text-text">Compra #{{ $compra->id() }}</h1>
                <span class="rounded-full bg-primary-soft px-3 py-1 text-xs font-medium text-primary-dark">{{ $compra->origen() === 'BOLETA' ? 'Con foto de boleta' : 'Registro manual' }}</span>
            </div>

            <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-4">
                <div>
                    <dt class="{{ $rotulo }}">Proveedor</dt>
                    <dd class="mt-1 text-sm font-semibold text-text">{{ $compra->proveedorComercial() ?? '—' }}</dd>
                    @if ($compra->ruc())
                        <dd class="text-xs text-text-muted">RUC {{ $compra->ruc() }}</dd>
                    @endif
                </div>
                <div>
                    <dt class="{{ $rotulo }}">Boleta</dt>
                    <dd class="mt-1 text-sm font-semibold text-text">{{ $compra->numeroComprobante() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="{{ $rotulo }}">Fecha</dt>
                    <dd class="mt-1 text-sm font-semibold text-text">{{ $compra->fecha()->format('d/m/Y') }}</dd>
                    @if ($compra->total() !== null)
                        <dd class="text-xs text-text-muted">Total S/ {{ number_format($compra->total(), 2) }}</dd>
                    @endif
                </div>
                <div>
                    <dt class="{{ $rotulo }}">Registrado por</dt>
                    <dd class="mt-1 text-sm font-semibold text-text">{{ $detalle['registrado_por'] }}</dd>
                    @if ($compra->registradoEn())
                        <dd class="text-xs text-text-muted">{{ $compra->registradoEn()->format('d/m/Y H:i') }}</dd>
                    @endif
                </div>
            </dl>

            @if ($compra->observacion())
                <p class="mt-4 text-sm text-text-muted"><span class="font-medium text-text">Observación:</span> {{ $compra->observacion() }}</p>
            @endif
        </header>

        <div class="grid border-t border-text-muted/10 lg:grid-cols-5">
            <section class="p-5 sm:p-6 lg:col-span-3">
                <h2 class="{{ $rotulo }}">Detalle</h2>
                <ul class="mt-3 divide-y divide-text-muted/10">
                    @foreach ($detalle['lineas'] as $linea)
                        <li class="flex items-baseline justify-between gap-3 py-3">
                            <div>
                                <p class="text-sm font-semibold text-text">{{ $linea['insumo'] }}</p>
                                <p class="text-xs text-text-muted">
                                    {{ $linea['presentacion'] }}
                                    @if ($linea['precio_unitario'] !== null)
                                        · S/ {{ number_format($linea['precio_unitario'], 2) }} c/u = S/ {{ number_format($linea['subtotal'], 2) }}
                                    @endif
                                </p>
                            </div>
                            <span class="whitespace-nowrap text-sm font-semibold text-success">+{{ $fmt($linea['cantidad_base']) }} {{ $linea['unidad_base'] }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 flex items-center gap-1.5 text-xs text-success">
                    <x-ui.icon name="check-circle" class="h-4 w-4 shrink-0" />
                    Estas cantidades ya ingresaron al stock de cada insumo.
                </p>
            </section>

            <section class="border-t border-text-muted/10 p-5 sm:p-6 lg:col-span-2 lg:border-l lg:border-t-0">
                <h2 class="{{ $rotulo }}">Boleta</h2>
                @if ($imagenUrl)
                    <a href="{{ $imagenUrl }}" target="_blank" rel="noopener" class="mt-3 block">
                        <img src="{{ $imagenUrl }}" alt="Boleta de la compra #{{ $compra->id() }}" class="max-h-96 w-full rounded-xl border border-text-muted/15 bg-background object-contain">
                    </a>
                @else
                    <p class="mt-3 text-sm text-text-muted">Compra registrada sin foto de boleta.</p>
                @endif
            </section>
        </div>
    </article>
@endsection
