<?php

namespace App\Application\Pago;

/** Totales recalculados tras ajustar la bonificación/descuento de un trabajador. */
final readonly class AjusteObligacionPersonalResultado
{
    public function __construct(
        /** Pese al nombre, es el acumulado a la fecha (días trabajados x valor por día), no el sueldo mensual completo. */
        public float $sueldoBase,
        public float $bonificacion,
        public float $descuento,
        public float $total,
    ) {}
}
