@props(['controlPeriodo'])

{{--
    Resumen del mes de un trabajador: SOLO muestra montos que ya trajo el
    backend (ControlPeriodoTrabajador / ObtenerControlPeriodoTrabajador).
    Nunca recalcula dinero aquí.
--}}
@php
    $soles = fn ($valor): string => 'S/ '.number_format((float) $valor, 2);
    $cp = $controlPeriodo;
@endphp

<x-ui.profile-section title="Resumen del mes" icon="banknote" {{ $attributes }}>
    @if (! $cp->tieneObligacion())
        <p class="text-sm text-text-muted">No hay registro de nómina para este mes.</p>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.data-field label="Asistencias">{{ $cp->asistencias }}</x-ui.data-field>
            <x-ui.data-field label="Faltas">{{ $cp->faltas }}</x-ui.data-field>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <x-ui.data-field label="Sueldo mensual">{{ $soles($cp->sueldoMensual) }}</x-ui.data-field>
            <x-ui.data-field label="Valor por día" muted>{{ $soles($cp->valorDia) }}<span class="ml-1 text-xs text-text-muted">(sueldo mensual ÷ 30)</span></x-ui.data-field>
        </div>

        @if ($cp->esMesCompleto && $cp->descuentoPorFaltas > 0)
            <div class="mt-4">
                <x-ui.data-field label="Descuento por faltas">− {{ $soles($cp->descuentoPorFaltas) }}</x-ui.data-field>
            </div>
        @endif

        <div class="mt-4 rounded-lg bg-background/60 p-3">
            <x-ui.data-field label="Acumulado">
                {{ $soles($cp->acumulado) }}
                <p class="mt-0.5 text-xs font-normal text-text-muted">
                    @if ($cp->esMesCompleto)
                        Sueldo mensual menos faltas registradas.
                    @else
                        Pago proporcional según los días trabajados desde su fecha de ingreso ({{ $cp->fechaIngresoEtiqueta }}).
                    @endif
                </p>
            </x-ui.data-field>
        </div>

        @if ($cp->bonificacion > 0 || $cp->descuento > 0)
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <x-ui.data-field label="Bonificaciones" muted>{{ $soles($cp->bonificacion) }}</x-ui.data-field>
                <x-ui.data-field label="Otros descuentos" muted>{{ $soles($cp->descuento) }}</x-ui.data-field>
            </div>
            <div class="mt-3 flex items-center justify-between rounded-lg bg-primary-soft/40 p-3">
                <span class="text-sm font-semibold text-text">Total final</span>
                <span class="text-xl font-bold text-primary-dark">{{ $soles($cp->total) }}</span>
            </div>
        @endif

        <div class="mt-4">
            <x-ui.badge :status="$cp->estado">{{ $cp->estadoEtiqueta }}</x-ui.badge>
            @if ($cp->estaEnCurso())
                <p class="mt-2 text-xs text-text-muted">El mes aún no ha sido cerrado.</p>
            @endif
        </div>
    @endif
</x-ui.profile-section>
