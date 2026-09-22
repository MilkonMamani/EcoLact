@props(['status' => null, 'variant' => null, 'icon' => null])

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

        // Genéricos
        'SUCCESS' => 'bg-success/10 text-success ring-success/20',
        'WARNING' => 'bg-warning/10 text-warning ring-warning/20',
        'DANGER' => 'bg-danger/10 text-danger ring-danger/20',
        'INFO' => 'bg-info/10 text-info ring-info/20',
    ];

    $classes = $palette[$key] ?? 'bg-text-muted/10 text-text-muted ring-text-muted/20';
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset', $classes]) }}>
    @if ($icon)
        <x-ui.icon :name="$icon" class="h-3.5 w-3.5" />
    @endif
    {{ $slot->isEmpty() ? str($key)->replace('_', ' ')->title() : $slot }}
</span>
