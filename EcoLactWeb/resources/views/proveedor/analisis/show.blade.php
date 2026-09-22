@php
    $entrega = $registro->entrega;
    $motivos = [
        'ACEPTADA' => 'Los parámetros analizados cumplen con los rangos establecidos.',
        'OBSERVADA' => 'Se identificó un incumplimiento leve según las reglas de calidad.',
        'RECHAZADA' => 'Incumplimiento de los parámetros establecidos.',
    ];
@endphp

@extends('layouts.proveedor')

@section('title', 'Análisis #' . $analisis->id())

@section('content')
    <x-ui.button :href="route('proveedor.analisis.index')" variant="ghost" size="sm" as="a">&larr; Volver a mi calidad</x-ui.button>

    <x-ui.card :title="'Análisis #' . $analisis->id()" subtitle="Resultado del análisis de calidad de tu entrega.">
        <x-slot:actions>
            <x-ui.badge :status="$analisis->estado()->value" />
            @if ($analisis->resultado())
                <x-ui.badge :status="$analisis->resultado()->value" />
            @endif
        </x-slot:actions>

        @if ($analisis->resultado())
            <p class="my-3 text-center text-xl font-semibold">
                <x-ui.badge :status="$analisis->resultado()->value" class="px-4 py-2 text-base" />
            </p>
            <p class="text-center text-sm text-text-muted">{{ $motivos[$analisis->resultado()->value] }}</p>

            @if ($analisis->resultado()->value === 'RECHAZADA')
                <div class="mt-3 rounded-lg bg-danger/10 p-3 text-sm text-danger">
                    <p class="font-semibold">✕ Esta entrega no cumple con los criterios de calidad establecidos.</p>
                    <p class="mt-1">Litros entregados: {{ $entrega ? number_format((float) $entrega->litros, 2) : '0.00' }} L · Litros pagables: 0.00 L</p>
                </div>
            @endif
        @else
            <p class="my-3 text-center text-sm text-text-muted">Aún sin resultado: el análisis está en borrador.</p>
        @endif

        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Entrega</dt>
                <dd class="mt-1 text-sm text-text"><a class="text-primary hover:underline" href="{{ route('proveedor.entregas.show', $analisis->entregaId()) }}">Entrega #{{ $analisis->entregaId() }}</a></dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Litros entregados</dt>
                <dd class="mt-1 text-sm text-text">{{ $entrega ? number_format((float) $entrega->litros, 2) : '0.00' }} L</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Fecha de análisis</dt>
                <dd class="mt-1 text-sm text-text">{{ $analisis->fechaAnalisis()->format('d/m/Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-text-muted">Estado</dt>
                <dd class="mt-1 text-sm text-text"><x-ui.badge :status="$analisis->estado()->value" /></dd>
            </div>
        </dl>
    </x-ui.card>

    <x-ui.card title="Parámetros de calidad" subtitle="Revisa los valores obtenidos en el análisis.">
        @if ($registro->detalles->isEmpty())
            <x-ui.empty-state title="Sin parámetros registrados" :icon="false" />
        @else
            <x-tables.table>
                <thead>
                    <tr class="border-b border-text-muted/10 text-xs uppercase tracking-wide text-text-muted">
                        <th class="py-2 pr-4">Parámetro</th>
                        <th class="py-2 pr-4">Valor</th>
                        <th class="py-2 pr-4">Unidad</th>
                        <th class="py-2 pr-4">Resultado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($registro->detalles as $detalle)
                        <tr class="border-b border-text-muted/5 last:border-0">
                            <td class="py-2 pr-4 text-text">{{ $detalle->parametro->nombre }}</td>
                            <td class="py-2 pr-4 text-text">{{ number_format((float) $detalle->valor, 2) }}</td>
                            <td class="py-2 pr-4 text-text-muted">{{ $detalle->parametro->unidad ?? '—' }}</td>
                            <td class="py-2 pr-4">
                                @if ($detalle->cumple === null)
                                    <span class="text-xs text-text-muted">—</span>
                                @elseif ($detalle->cumple)
                                    <span class="text-success">✓ Cumple</span>
                                @else
                                    <span class="text-danger">✕ Fuera de rango</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-tables.table>
        @endif
    </x-ui.card>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-ui.card title="Observación">
            <p class="text-sm text-text">{{ $analisis->observacion() ?? 'No se registraron observaciones para este análisis.' }}</p>
        </x-ui.card>

        <x-ui.card title="Evidencia del análisis">
            @if ($analisis->imagenPath())
                <img src="{{ asset('storage/'.$analisis->imagenPath()) }}" alt="Imagen del análisis de calidad" class="max-w-xs rounded-lg border border-text-muted/10">
            @else
                <p class="text-sm text-text-muted">Sin imagen adjunta.</p>
            @endif
        </x-ui.card>
    </div>

    @if ($sancion)
        <x-ui.card title="Seguimiento de calidad" subtitle="Advertencias registradas relacionadas con este análisis.">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-text-muted">Advertencia</dt><dd class="text-text">{{ $sancion->nivel_advertencia ? $sancion->nivel_advertencia.'.ª advertencia' : '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-text-muted">Fecha</dt><dd class="text-text">{{ $sancion->fecha->format('d/m/Y') }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-text-muted">Motivo</dt><dd class="text-text">{{ $sancion->descripcion }}</dd></div>
            </dl>
        </x-ui.card>
    @endif
@endsection
