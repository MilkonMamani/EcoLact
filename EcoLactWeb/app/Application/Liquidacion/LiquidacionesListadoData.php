<?php

namespace App\Application\Liquidacion;

/**
 * @phpstan-type Fila array{id: int, proveedorNombre: string, proveedorDni: string, periodoDesde: string, periodoHasta: string, litrosEntregados: float, litrosPagables: float, precioAplicado: float, tuvoIncidencia: bool, totalPagar: float, estado: string}
 */
final readonly class LiquidacionesListadoData
{
    /**
     * @param  array{proveedoresLiquidados: int, litrosEntregados: float, totalPagar: float, pendientesPago: int}  $kpis
     * @param  list<array{id: int, nombre: string}>  $zonas
     * @param  list<array{id: int, label: string}>  $periodos
     * @param  list<Fila>  $filas
     */
    public function __construct(
        public array $kpis,
        public array $zonas,
        public array $periodos,
        public array $filas,
    ) {}
}
