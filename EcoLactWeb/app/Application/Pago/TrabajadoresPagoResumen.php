<?php

namespace App\Application\Pago;

/**
 * Franja de métricas de "Pagos a trabajadores" para los filtros activos: cuántos
 * trabajadores hay en el mes, cuántos pagos esperan registrarse y por cuánto. Los
 * montos son los de las obligaciones (snapshot), nunca un cálculo de la vista.
 */
final readonly class TrabajadoresPagoResumen
{
    public function __construct(
        public int $trabajadores,
        public int $pendientes,
        public int $pagados,
        public float $totalPendiente,
    ) {}
}
