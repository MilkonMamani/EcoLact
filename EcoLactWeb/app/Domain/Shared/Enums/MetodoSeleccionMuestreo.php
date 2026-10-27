<?php

namespace App\Domain\Shared\Enums;

/**
 * Cómo llegó una entrega a control de calidad:
 * - ALEATORIA: el servidor la eligió al azar entre las entregas elegibles;
 * - MANUAL: el analista la eligió de la lista (rutinario o dirigido, ver TipoControlMuestreo);
 * - DIRECTA: el analista tomó la muestra en campo antes del acopio (RegistrarEntregaMuestreada).
 */
enum MetodoSeleccionMuestreo: string
{
    case ALEATORIA = 'ALEATORIA';
    case MANUAL = 'MANUAL';
    case DIRECTA = 'DIRECTA';
}
