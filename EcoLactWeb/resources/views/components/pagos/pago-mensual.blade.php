@props(['controlPeriodo', 'dni' => null])

{{--
    "Pago mensual" de un trabajador: resumen de CONSULTA (sin calendario, sin registro de
    asistencia, sin acciones de pago). Solo pinta lo que ya calculó el backend en
    ObtenerControlPeriodoTrabajador (snapshot de obligaciones_pago + conteo de jornadas);
    nunca recalcula dinero aquí. Pagar/ajustar se hace en Pagar a trabajadores, al que
    enlaza el pie (la obligación del mes seleccionada, o el mes filtrado por DNI si aún no hay).
--}}
@php
    $cp = $controlPeriodo;
    $soles = fn ($valor): string => 'S/ '.number_format((float) $valor, 2);
    $urlModulo = $cp?->tieneObligacion()
        ? route('admin.pagos.trabajadores.index', ['periodo' => $cp->mesValor, 'trabajador' => $cp->obligacionId])
        : route('admin.pagos.trabajadores.index', array_filter(['periodo' => $cp?->mesValor, 'q' => $dni]));
    $filas = $cp === null ? [] : [
        ['Sueldo mensual', $soles($cp->sueldoMensual), null],
        ['Valor por día', $soles($cp->valorDia), 'Sueldo mensual ÷ 30'],
        ['Asistencias', $cp->asistencias, null],
        ['Faltas', $cp->faltas, null],
        ['Acumulado', $soles($cp->acumulado), match (true) {
            ! $cp->controlaAsistencia => 'Sueldo fijo mensual (sin control de asistencia).',
            ! $cp->esMesCompleto => 'Pago proporcional según los días trabajados desde su fecha de ingreso ('.$cp->fechaIngresoEtiqueta.').',
            default => 'Sueldo mensual menos faltas registradas.',
        }],
        ['Bonificación', $soles($cp->bonificacion), null],
        ['Descuentos', $soles($cp->descuento), null],
    ];
@endphp

<div data-pago-mensual {{ $attributes->class('text-text') }}>
    @if ($cp === null)
        <p class="rounded-lg border border-dashed border-text-muted/25 px-4 py-6 text-center text-sm text-text-muted">Este trabajador no tiene una identidad laboral registrada todavía.</p>
    @else
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-base font-bold">Pago mensual</h3>
            <x-ui.badge :status="$cp->estado" data-pago-estado>{{ $cp->estadoEtiqueta }}</x-ui.badge>
        </div>

        <div class="mt-4 text-center">
            <div class="flex items-center justify-center gap-2">
                <a href="{{ request()->fullUrlWithQuery(['mes' => $cp->mesAnteriorValor]) }}" aria-label="Mes anterior" title="Mes anterior" class="rounded-lg p-1.5 text-text-muted transition hover:bg-text-muted/10 hover:text-text">
                    <x-ui.icon name="chevron-left" class="h-4 w-4" />
                </a>
                <p class="min-w-36 text-sm font-semibold" data-pago-mes>{{ $cp->periodoEtiqueta }}</p>
                @if ($cp->mesSiguienteValor)
                    <a href="{{ request()->fullUrlWithQuery(['mes' => $cp->mesSiguienteValor]) }}" aria-label="Mes siguiente" title="Mes siguiente" class="rounded-lg p-1.5 text-text-muted transition hover:bg-text-muted/10 hover:text-text">
                        <x-ui.icon name="chevron-right" class="h-4 w-4" />
                    </a>
                @else
                    <span class="p-1.5 text-text-muted/30" aria-hidden="true"><x-ui.icon name="chevron-right" class="h-4 w-4" /></span>
                @endif
            </div>
            <p class="mt-1 text-xs text-text-muted">Periodo: {{ $cp->periodoRango }}</p>
        </div>

        @if (! $cp->tieneObligacion())
            <p class="mt-4 rounded-lg border border-dashed border-text-muted/25 px-4 py-6 text-center text-sm text-text-muted">No hay registro de nómina para este mes.</p>
        @else
            <dl class="mt-4 divide-y divide-text-muted/10 border-y border-text-muted/10 text-sm">
                @foreach ($filas as [$etiqueta, $valor, $nota])
                    <div class="flex items-start justify-between gap-4 py-2.5">
                        <dt class="min-w-0">
                            <span class="text-text-muted">{{ $etiqueta }}</span>
                            @if ($nota)
                                <span class="mt-0.5 block text-xs text-text-muted/80">{{ $nota }}</span>
                            @endif
                        </dt>
                        <dd class="shrink-0 font-semibold tabular-nums">{{ $valor }}</dd>
                    </div>
                @endforeach
            </dl>

            <div class="mt-3 flex items-center justify-between gap-4 rounded-lg bg-primary-soft/40 px-3 py-3">
                <span class="text-sm font-semibold">Total final</span>
                <span class="text-xl font-bold tabular-nums text-primary-dark" data-pago-total>{{ $soles($cp->total) }}</span>
            </div>
        @endif

        <a href="{{ $urlModulo }}" class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary-dark hover:underline" data-pago-modulo>
            Ver en módulo de pagos <span aria-hidden="true">→</span>
        </a>
    @endif
</div>
