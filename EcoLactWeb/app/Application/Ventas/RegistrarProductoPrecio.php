<?php

namespace App\Application\Ventas;

use App\Domain\Produccion\ProductoRepository;
use App\Domain\Ventas\ProductoPrecio;
use App\Domain\Ventas\ProductoPrecioRepository;
use DateTimeImmutable;
use DomainException;

/**
 * Fija el precio de un producto para un tipo de cliente (PROVEEDOR,
 * MAYORISTA o PUBLICO). Cada tipo de cliente paga un precio distinto por el
 * mismo producto (ej. queso a S/18 al proveedor, S/19 al mayorista, S/20 al
 * público).
 */
final class RegistrarProductoPrecio
{
    public function __construct(
        private readonly ProductoRepository $productos,
        private readonly ProductoPrecioRepository $precios,
    ) {}

    public function execute(
        int $productoId,
        string $tipoCliente,
        float $precio,
        int $creadoPorUserId,
        ?DateTimeImmutable $fechaInicio = null,
    ): ProductoPrecio {
        if ($this->productos->buscarPorId($productoId) === null) {
            throw new DomainException('El producto indicado no existe.');
        }

        return $this->precios->guardar(new ProductoPrecio(
            id: null,
            productoId: $productoId,
            tipoCliente: $tipoCliente,
            precio: $precio,
            fechaInicio: $fechaInicio ?? new DateTimeImmutable,
            fechaFin: null,
            estado: 'ACTIVO',
            creadoPorUserId: $creadoPorUserId,
        ));
    }
}
