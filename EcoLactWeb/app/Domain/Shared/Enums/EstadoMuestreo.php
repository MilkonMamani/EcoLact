<?php

namespace App\Domain\Shared\Enums;

/**
 * Ciclo de vida de una muestra de calidad, independiente de EstadoEntrega.
 * "No seleccionada" no es un estado guardado: es la ausencia de muestreo.
 */
enum EstadoMuestreo: string
{
    case SELECCIONADA = 'SELECCIONADA';
    case EN_ANALISIS = 'EN_ANALISIS';
    case ANALIZADA = 'ANALIZADA';

    /** La entrega se anuló antes de analizarse: la muestra deja de estar pendiente. */
    case CANCELADA = 'CANCELADA';

    /** Cuenta como "muestra pendiente" (todavía hay que analizarla). */
    public function estaPendiente(): bool
    {
        return $this === self::SELECCIONADA || $this === self::EN_ANALISIS;
    }
}
