<?php

namespace App\Application\Produccion;

/**
 * @phpstan-type Movimiento array{fecha: string, tipo: string, cantidad: float, origen: string}
 */
final readonly class DetalleStockData
{
    /**
     * @param  list<Movimiento>  $movimientos
     */
    public function __construct(
        public string $tipo,
        public int $id,
        public string $nombre,
        public string $unidadMedida,
        public float $cantidadActual,
        public ?float $stockMinimo,
        public string $estado,
        public array $movimientos,
    ) {}
}
