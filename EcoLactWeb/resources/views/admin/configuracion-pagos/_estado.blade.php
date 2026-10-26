{{-- Estado de un sueldo: ACTIVO | PROGRAMADO | SIN_CONFIGURAR | INACTIVO. --}}
@php
    [$clase, $texto] = match ($estado) {
        'ACTIVO' => ['is-activo', 'Activo'],
        'PROGRAMADO' => ['is-programado', 'Programado'],
        'INACTIVO' => ['is-inactivo', 'Inactivo'],
        default => ['is-pendiente', 'Pendiente'],
    };
@endphp
<span class="config-pagos-estado {{ $clase }}">{{ $texto }}</span>
