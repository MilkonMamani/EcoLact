<?php

namespace App\Application\Caja;

/**
 * Tarjetas superiores de Caja. El saldo es SUMA(entradas) − SUMA(salidas) de
 * todos los movimientos; "hoy" es el día calendario actual.
 */
final readonly class CajaResumen
{
    public function __construct(
        public float $saldo,
        public float $ingresosHoy,
        public float $salidasHoy,
        public int $movimientosHoy,
    ) {}
}
