@props(['estado'])

{{--
    Estado compacto de un pago de trabajador (EstadoObligacionPago): ícono + texto, sin badge.
    Pendiente = reloj ámbar, Pagado = doble check verde, En curso = mes aún abierto (gris
    verdoso), Sin configurar = gris neutro. Solo etiqueta el valor real que trae el backend.
--}}
@php
    [$etiqueta, $clase, $icono] = match ($estado) {
        'EN_CURSO' => ['En curso', 'is-en-curso', 'refresh'],
        'PENDIENTE' => ['Pendiente', 'is-pendiente', 'clock'],
        'PAGADO' => ['Pagado', 'is-pagado', 'check-double'],
        'SIN_SUELDO' => ['Sin configurar', 'is-neutro', 'minus-circle'],
        default => ['Sin registro', 'is-neutro', 'minus-circle'],
    };
@endphp

<span {{ $attributes->class(['pago-estado', $clase]) }} data-pago-estado="{{ $estado }}"><x-ui.icon :name="$icono" class="h-4 w-4" /><span>{{ $etiqueta }}</span></span>
