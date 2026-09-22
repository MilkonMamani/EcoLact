<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\InsumoRepository;
use DomainException;

/**
 * Devuelve el insumo del sistema LECHE, cuyo stock (siempre en litros) sube
 * con cada entrega de proveedores y baja con cada producción.
 */
final class ObtenerStockLeche
{
    public function __construct(
        private readonly InsumoRepository $insumos,
    ) {}

    public function execute(): Insumo
    {
        return $this->insumos->buscarPorCodigo(Insumo::CODIGO_LECHE)
            ?? throw new DomainException('El insumo del sistema Leche no está configurado.');
    }
}
