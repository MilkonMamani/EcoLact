@props(['estado'])

{{--
    Estado de un pago de trabajador (EstadoObligacionPago), con los colores propios de
    Pagos a trabajadores: En curso verde, Pendiente naranja, Pagado azul. Solo etiqueta
    el valor real que trae el backend.
--}}
@php
    [$etiqueta, $clase] = match ($estado) {
        'EN_CURSO' => ['En curso', 'is-en-curso'],
        'PENDIENTE' => ['Pendiente', 'is-pendiente'],
        'PAGADO' => ['Pagado', 'is-pagado'],
        'SIN_SUELDO' => ['Sin sueldo', 'is-neutro'],
        default => ['Sin registro', 'is-neutro'],
    };
@endphp

<span {{ $attributes->class(['pago-estado', $clase]) }} data-pago-estado="{{ $estado }}">{{ $etiqueta }}</span>
