<?php

namespace App\Application\Calidad;

/**
 * Estado de control de calidad de UNA entrega, tal como lo ve quien la
 * registró (acopiador). Calidad trabaja por muestreo: una entrega sin
 * muestra nunca está "pendiente de análisis", está "sin muestreo".
 *
 * Orden de decisión:
 * 1. entrega ANULADA → ANULADA;
 * 2. muestra todavía abierta (SELECCIONADA / EN_ANALISIS) → ese estado,
 *    aunque exista un borrador de análisis;
 * 3. análisis CONFIRMADO con resultado → ACEPTADA / OBSERVADA / RECHAZADA
 *    (incluye entregas analizadas antes de existir el muestreo);
 * 4. cualquier otro caso → SIN_MUESTREO.
 */
final class ControlCalidadEntrega
{
    public const SIN_MUESTREO = 'SIN_MUESTREO';

    public const SELECCIONADA = 'SELECCIONADA';

    public const EN_ANALISIS = 'EN_ANALISIS';

    public const ANULADA = 'ANULADA';

    public static function de(string $estadoEntrega, ?string $estadoMuestreo, ?string $resultadoConfirmado): string
    {
        if ($estadoEntrega === 'ANULADA') {
            return self::ANULADA;
        }

        if (in_array($estadoMuestreo, [self::SELECCIONADA, self::EN_ANALISIS], true)) {
            return $estadoMuestreo;
        }

        return $resultadoConfirmado ?? self::SIN_MUESTREO;
    }
}
