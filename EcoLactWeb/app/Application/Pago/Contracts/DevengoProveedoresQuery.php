<?php

namespace App\Application\Pago\Contracts;

use DateTimeImmutable;

/**
 * Leche recibida y valorizada en un rango, por fecha de la ENTREGA (no de
 * su pago). El valor de cada entrega es su línea en obligacion_pago_detalles
 * (SincronizarPagosProveedor), que ya aplica el precio vigente ese día y el
 * resultado de calidad de su semana; nada se recalcula aquí.
 *
 * - litrosRecibidos: entregas no anuladas.
 * - litrosEnAnalisis: con una muestra de calidad todavía SELECCIONADA o EN_ANALISIS.
 * - litrosValorizados: litros pagables de semanas pagables.
 * - litrosNoPagables: entregas en semanas NO_PAGABLE (RECHAZADA) o sin litros pagables.
 * - litrosSinValorizar: entregas que aún no tienen línea de pago (p. ej. sin precio o configuración).
 * - devengado: importe de las entregas valorizadas.
 * - sujetoACalidad: parte del devengado de semanas aún no pagadas con una
 *   muestra pendiente: puede cambiar al confirmarse el análisis.
 */
interface DevengoProveedoresQuery
{
    /**
     * @param  DateTimeImmutable  $desde  instante inicial, incluido
     * @param  DateTimeImmutable  $hasta  instante final, EXCLUIDO
     * @return array{entregas: int, litrosRecibidos: float, litrosEnAnalisis: float, litrosValorizados: float, litrosNoPagables: float, litrosSinValorizar: float, devengado: float, sujetoACalidad: float}
     */
    public function resumen(DateTimeImmutable $desde, DateTimeImmutable $hasta): array;
}
