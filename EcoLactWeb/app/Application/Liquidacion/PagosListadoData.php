<?php

namespace App\Application\Liquidacion;

final readonly class PagosListadoData
{
    /**
     * @param  array{pendientesPago: int, pagosRealizados: int, montoPendiente: float, montoPagado: float}  $kpis
     * @param  list<array{id: int, nombre: string}>  $zonas
     * @param  list<array<string, mixed>>  $filas  Una cuenta por proveedor con sus periodos pendientes.
     */
    public function __construct(
        public array $kpis,
        public array $zonas,
        public array $filas,
    ) {}
}
