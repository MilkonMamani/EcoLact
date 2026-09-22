<?php

namespace App\Application\Produccion;

/**
 * @phpstan-type Fila array{id: int, codigo: string, inicio: string, fin: ?string, litros: float, productos: int, estado: string}
 */
final readonly class LotesProduccionListadoData
{
    /**
     * @param  array{enProceso: int, finalizados: int, lecheUtilizada: float, productosObtenidos: float}  $kpis
     * @param  list<Fila>  $filas
     */
    public function __construct(
        public array $kpis,
        public array $filas,
    ) {}
}
