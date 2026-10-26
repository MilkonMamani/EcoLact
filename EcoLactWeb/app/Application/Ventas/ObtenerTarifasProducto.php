<?php

namespace App\Application\Ventas;

use App\Domain\Ventas\ProductoPrecioRepository;
use App\Domain\Ventas\TarifasProducto;
use DateTimeImmutable;

/**
 * Los tres precios vigentes de un producto en una fecha. La misma lectura
 * alimenta la previsualización de la venta y su registro.
 */
final class ObtenerTarifasProducto
{
    public function __construct(
        private readonly ProductoPrecioRepository $precios,
    ) {}

    public function execute(int $productoId, ?DateTimeImmutable $fecha = null): TarifasProducto
    {
        $fecha ??= new DateTimeImmutable;

        return new TarifasProducto(
            publico: $this->precios->buscarVigente($productoId, 'PUBLICO', $fecha),
            mayorista: $this->precios->buscarVigente($productoId, 'MAYORISTA', $fecha),
            proveedor: $this->precios->buscarVigente($productoId, 'PROVEEDOR', $fecha),
        );
    }
}
