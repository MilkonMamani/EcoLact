@php
    $entrega = $registro->entrega;
    $proveedor = $entrega?->proveedor;
    $motivos = [
        'ACEPTADA' => 'Los parámetros analizados cumplen con los rangos establecidos.',
        'OBSERVADA' => 'Se identificó un incumplimiento leve según las reglas de calidad.',
        'RECHAZADA' => 'Incumplimiento de los parámetros establecidos.',
    ];
@endphp

@extends('layouts.calidad')

@section('title', 'Análisis #A-' . str_pad((string) $analisis->id(), 6, '0', STR_PAD_LEFT))

@section('content')
    <x-ui.button :href="route('calidad.analisis-calidad.index', ['tab' => 'historial'])" variant="ghost" size="sm" as="a">&larr; Historial</x-ui.button>

    <x-ui.card :title="'Análisis #A-' . str_pad((string) $analisis->id(), 6, '0', STR_PAD_LEFT)" subtitle="Estado del análisis y resultado de calidad, dos conceptos distintos.">
        <x-slot:actions>
            <x-ui.badge :status="$analisis->estado()->value" />
            @if ($analisis->resultado())
                <x-ui.badge :status="$analisis->resultado()->value" />
            @endif
        </x-slot:actions>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-text-muted/10 p-4">
                <h3 class="mb-3 text-sm font-semibold text-text">Datos de la entrega</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">ID de entrega</dt><dd><a class="text-primary hover:underline" href="{{ route('calidad.entregas.show', $analisis->entregaId()) }}">#{{ $analisis->entregaId() }}</a></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Fecha y hora</dt><dd class="text-text">{{ $entrega?->fecha_hora?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Proveedor</dt><dd class="text-text">{{ $proveedor ? trim($proveedor->nombres.' '.$proveedor->apellidos) : '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">DNI</dt><dd class="text-text">{{ $proveedor?->dni ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Zona</dt><dd class="text-text">{{ $entrega?->zona?->nombre ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Acopiador</dt><dd class="text-text">{{ $entrega?->acopiador ? trim($entrega->acopiador->nombres.' '.$entrega->acopiador->apellidos) : '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Litros entregados</dt><dd class="text-text">{{ $entrega ? number_format((float) $entrega->litros, 2) : '0.00' }} L</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Identificación</dt><dd class="text-text">{{ $entrega?->metodo_identificacion ?? '—' }}</dd></div>
                </dl>
            </div>

            <div class="rounded-xl border border-text-muted/10 p-4">
                <h3 class="mb-1 text-sm font-semibold text-text">Resultado de calidad</h3>

                @if ($analisis->resultado())
                    <p class="my-3 text-center text-xl font-semibold">
                        <x-ui.badge :status="$analisis->resultado()->value" class="px-4 py-2 text-base" />
                    </p>

                    <p class="text-xs uppercase tracking-wide text-text-muted">Motivo</p>
                    <p class="mb-3 text-sm text-text">{{ $motivos[$analisis->resultado()->value] }}</p>

                    @if ($analisis->resultado()->value === 'RECHAZADA')
                        <div class="mb-3 rounded-lg bg-danger/10 p-3 text-sm text-danger">
                            <p class="font-semibold">✕ Leche rechazada — la entrega no es pagable.</p>
                            <p class="mt-1">Litros entregados: {{ $entrega ? number_format((float) $entrega->litros, 2) : '0.00' }} L · Litros pagables: 0.00 L</p>
                        </div>
                    @elseif ($analisis->resultado()->value === 'OBSERVADA')
                        <div class="mb-3 rounded-lg bg-warning/10 p-3 text-sm text-warning">⚠ Leche observada: se identificó un incumplimiento leve según las reglas de calidad.</div>
                    @else
                        <div class="mb-3 rounded-lg bg-success/10 p-3 text-sm text-success">✓ Leche aceptada.</div>
                    @endif
                @else
                    <p class="my-3 text-sm text-text-muted">Aún sin resultado: el análisis está en borrador.</p>
                @endif

                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Fecha del análisis</dt><dd class="text-text">{{ $analisis->fechaAnalisis()->format('d/m/Y H:i') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Analizado por</dt><dd class="text-text">{{ $registro->confirmadoPor?->username ?? 'Pendiente de confirmación' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-text-muted">Estado</dt><dd><x-ui.badge :status="$analisis->estado()->value" /></dd></div>
                </dl>
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-text-muted/10 p-4">
            <h3 class="mb-3 text-sm font-semibold text-text">Parámetros analizados</h3>

            @if ($registro->detalles->isEmpty())
                <x-ui.empty-state title="Sin parámetros registrados" :icon="false" />
            @else
                <x-tables.table>
                    <thead>
                        <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                            <th class="py-2 pr-4">Parámetro</th>
                            <th class="py-2 pr-4">Valor</th>
                            <th class="py-2 pr-4">Unidad</th>
                            <th class="py-2 pr-4">Origen</th>
                            <th class="py-2 pr-4">Cumple</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($registro->detalles as $detalle)
                            <tr class="border-b border-text-muted/5 last:border-0">
                                <td class="py-2 pr-4 text-text">{{ $detalle->parametro->nombre }}</td>
                                <td class="py-2 pr-4 text-text">{{ number_format((float) $detalle->valor, 2) }}</td>
                                <td class="py-2 pr-4 text-text-muted">{{ $detalle->parametro->unidad ?? '—' }}</td>
                                <td class="py-2 pr-4 text-text-muted">{{ $detalle->origen }}</td>
                                <td class="py-2 pr-4">
                                    @if ($detalle->cumple === null)
                                        <span class="text-xs text-text-muted">—</span>
                                    @elseif ($detalle->cumple)
                                        <span class="text-success">✓ Sí</span>
                                    @else
                                        <span class="text-danger">✕ No</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-tables.table>
            @endif
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-text-muted/10 p-4">
                <h3 class="mb-2 text-sm font-semibold text-text">Evidencia del análisis</h3>

                @if ($analisis->imagenPath())
                    <img src="{{ asset('storage/'.$analisis->imagenPath()) }}" alt="Imagen del análisis de calidad" class="max-w-xs rounded-lg border border-text-muted/10">
                    <a class="mt-2 inline-block text-sm text-primary hover:underline" href="{{ asset('storage/'.$analisis->imagenPath()) }}" target="_blank" rel="noopener">Ver imagen completa</a>
                @else
                    <p class="text-sm text-text-muted">Sin imagen adjunta.</p>
                @endif
            </div>

            <div class="rounded-xl border border-text-muted/10 p-4">
                <h3 class="mb-2 text-sm font-semibold text-text">Observación</h3>
                <p class="text-sm text-text">{{ $analisis->observacion() ?? 'Sin observaciones registradas.' }}</p>
            </div>
        </div>

        <div class="mt-4">
            @if ($analisis->estaConfirmado())
                <p class="text-sm text-text-muted">Análisis confirmado. No puede modificarse.</p>
            @else
                <div class="rounded-xl border border-warning/30 bg-warning/5 p-4">
                    <p class="text-sm font-semibold text-text">Análisis en borrador</p>
                    <p class="mt-1 text-sm text-text-muted">Verifica los valores antes de confirmar. Después de confirmar, el análisis quedará bloqueado para modificaciones.</p>

                    @can('update', $registro)
                        <form method="POST" action="{{ route('calidad.analisis-calidad.finalizar', $analisis->id()) }}" class="mt-3" onsubmit="return confirm('¿Confirmar análisis? Después de confirmar no podrás modificar los valores ni el resultado.');">
                            @csrf
                            @method('PATCH')
                            <x-ui.button type="submit" variant="primary">Confirmar análisis</x-ui.button>
                        </form>
                    @endcan
                </div>
            @endif
        </div>
    </x-ui.card>
@endsection
