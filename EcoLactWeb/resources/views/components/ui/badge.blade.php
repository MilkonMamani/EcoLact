@props(['status' => null, 'variant' => null, 'icon' => null, 'compact' => false])

@php
    $key = strtoupper((string) ($variant ?? $status ?? ''));

    $palette = [
        // Estados generales
        'ACTIVO' => 'bg-success/10 text-success ring-success/20',
        'INACTIVO' => 'bg-text-muted/10 text-text-muted ring-text-muted/20',

        // Calidad
        'ACEPTADA' => 'bg-success/10 text-success ring-success/20',
        'OBSERVADA' => 'bg-warning/10 text-warning ring-warning/20',
        'RECHAZADA' => 'bg-danger/10 text-danger ring-danger/20',

        // Análisis / Comunicados
        'BORRADOR' => 'bg-info/10 text-info ring-info/20',
        'CONFIRMADO' => 'bg-success/10 text-success ring-success/20',
        'PUBLICADO' => 'bg-success/10 text-success ring-success/20',

        // Entregas
        'PENDIENTE_ANALISIS' => 'bg-warning/10 text-warning ring-warning/20',
        'ANALIZADA' => 'bg-success/10 text-success ring-success/20',
        'ANULADA' => 'bg-danger/10 text-danger ring-danger/20',
        // Muestreo de calidad
        'SELECCIONADA' => 'bg-warning/10 text-warning ring-warning/20',
        'EN_ANALISIS' => 'bg-info/10 text-info ring-info/20',
        'CANCELADA' => 'bg-text-muted/10 text-text-muted ring-text-muted/20',

        // Productos terminados
        'DISPONIBLE' => 'bg-success/10 text-success ring-success/20',
        'SIN_STOCK' => 'bg-warning/10 text-warning ring-warning/20',
        'CONFIGURADA' => 'bg-success/10 text-success ring-success/20',
        'SIN_RECETA' => 'bg-danger/10 text-danger ring-danger/20',

        // Recetas
        'ACTIVA' => 'bg-success/10 text-success ring-success/20',
        'INACTIVA' => 'bg-text-muted/10 text-text-muted ring-text-muted/20',

        // Producción
        'EN_PROCESO' => 'bg-info/10 text-info ring-info/20',
        'FINALIZADO' => 'bg-success/10 text-success ring-success/20',

        // Recorridos de acopio / paradas
        'EN_PROGRESO' => 'bg-info/10 text-info ring-info/20',
        'CANCELADO' => 'bg-danger/10 text-danger ring-danger/20',
        'RECOLECTADO' => 'bg-success/10 text-success ring-success/20',
        'NO_ENTREGO' => 'bg-warning/10 text-warning ring-warning/20',

        // Liquidaciones
        'EN_CURSO' => 'bg-info/10 text-info ring-info/20',
        'PENDIENTE' => 'bg-warning-soft text-warning ring-warning/25',
        'PARCIALMENTE_PAGADA' => 'bg-info/10 text-info ring-info/20',
        'PAGADA' => 'bg-success/10 text-success ring-success/20',
        'PAGADO' => 'bg-success-soft text-success ring-success/25',
        'PROGRAMADA' => 'bg-info/10 text-info ring-info/20',
        'VENCIDA' => 'bg-text-muted/10 text-text-muted ring-text-muted/20',
        'SIN_CONFIGURAR' => 'bg-text-muted/10 text-text-muted ring-text-muted/20',

        // Pagos
        'REGISTRADO' => 'bg-info/10 text-info ring-info/20',

        // Asistencia
        'PRESENTE' => 'bg-success/10 text-success ring-success/20',
        'FALTA' => 'bg-danger/10 text-danger ring-danger/20',
        'SIN_REGISTRO' => 'bg-text-muted/10 text-text-muted ring-text-muted/20',
        'FUTURO' => 'bg-text-muted/10 text-text-muted ring-text-muted/20',
        'NO_APLICABLE' => 'bg-text-muted/10 text-text-muted ring-text-muted/20',

        // Genéricos
        'SUCCESS' => 'bg-success/10 text-success ring-success/20',
        'WARNING' => 'bg-warning/10 text-warning ring-warning/20',
        'DANGER' => 'bg-danger/10 text-danger ring-danger/20',
        'INFO' => 'bg-info/10 text-info ring-info/20',
    ];

    $classes = $palette[$key] ?? 'bg-text-muted/10 text-text-muted ring-text-muted/20';

    /*
     * Modo compacto (tablas y listados): solo un ícono con color semántico; el nombre del
     * estado queda en el tooltip (data-tooltip) y como texto para lectores de pantalla.
     * Un único mapeo para todo el sistema, así un mismo estado se ve igual en todas partes.
     */
    $tonos = [
        // Positivo / final correcto → doble check verde
        'positivo' => ['ANALIZADA', 'ACEPTADA', 'CONFIRMADO', 'PUBLICADO', 'FINALIZADO', 'RECOLECTADO', 'PAGADA', 'PAGADO', 'PRESENTE', 'ACTIVO', 'ACTIVA', 'DISPONIBLE', 'CONFIGURADA', 'REGISTRADA', 'CERRADO', 'SUCCESS'],
        // Pendiente / en curso → reloj ámbar
        'pendiente' => ['PENDIENTE_ANALISIS', 'SELECCIONADA', 'EN_ANALISIS', 'PENDIENTE', 'SIN_REGISTRO', 'BORRADOR', 'EN_CURSO', 'EN_PROCESO', 'EN_PROGRESO', 'PROGRAMADA', 'REGISTRADO', 'PARCIALMENTE_PAGADA', 'ABIERTO', 'INFO'],
        // Observación / requiere atención → advertencia amarilla
        'advertencia' => ['OBSERVADA', 'SIN_STOCK', 'NO_ENTREGO', 'WARNING'],
        // Negativo / rechazado → X roja
        'negativo' => ['RECHAZADA', 'ANULADA', 'ANULADO', 'CANCELADO', 'FALTA', 'SIN_RECETA', 'INACTIVO', 'INACTIVA', 'DANGER'],
        // Sin efecto (no aplica / vencido / sin configurar) → neutro gris
        'neutro' => ['CANCELADA', 'VENCIDA', 'SIN_CONFIGURAR', 'NO_APLICABLE', 'FUTURO'],
    ];
    $estilosCompactos = [
        'positivo' => ['check-double', 'is-positivo'],
        'pendiente' => ['clock', 'is-pendiente'],
        'advertencia' => ['triangle-alert', 'is-advertencia'],
        'negativo' => ['x-circle', 'is-negativo'],
        'neutro' => ['minus-circle', 'is-neutro'],
    ];
    $tono = collect($tonos)->search(fn (array $claves): bool => in_array($key, $claves, true)) ?: 'neutro';
    [$iconoCompacto, $claseCompacta] = $estilosCompactos[$tono];

    $nombres = [
        'PENDIENTE_ANALISIS' => 'Pendiente de análisis', 'SIN_REGISTRO' => 'Pendiente', 'NO_ENTREGO' => 'No entregó',
        'EN_CURSO' => 'En curso', 'EN_PROCESO' => 'En proceso', 'EN_PROGRESO' => 'En progreso', 'SIN_STOCK' => 'Sin stock',
        'SIN_RECETA' => 'Sin receta', 'SIN_CONFIGURAR' => 'Sin configurar', 'NO_APLICABLE' => 'No aplica',
        'PARCIALMENTE_PAGADA' => 'Parcialmente pagada',
        'SELECCIONADA' => 'Muestra pendiente', 'EN_ANALISIS' => 'En análisis',
    ];
    $etiqueta = $slot->isEmpty() ? ($nombres[$key] ?? str($key)->replace('_', ' ')->lower()->ucfirst()->toString()) : trim(strip_tags((string) $slot));
@endphp

@if ($compact)
    <span {{ $attributes->class(['estado-compacto', $claseCompacta]) }} data-tooltip="{{ $etiqueta }}" data-estado="{{ $key }}">
        <x-ui.icon :name="$iconoCompacto" class="h-4 w-4" />
        <span class="sr-only">{{ $etiqueta }}</span>
    </span>
@else
    <span {{ $attributes->class(['inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset', $classes]) }}>
        @if ($icon)
            <x-ui.icon :name="$icon" class="h-3.5 w-3.5" />
        @endif
        {{ $slot->isEmpty() ? str($key)->replace('_', ' ')->title() : $slot }}
    </span>
@endif
