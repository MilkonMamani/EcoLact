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
 *
 * Un tipo de cliente tiene un solo precio vigente: registrar uno nuevo cierra
 * el anterior (queda INACTIVO, con fecha_fin; las ventas pasadas lo citan).
 * El precio MAYORISTA puede llevar `cantidadMinima`: desde cuántas unidades
 * se le aplica también a una venta al público.
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
        ?float $cantidadMinima = null,
    ): ProductoPrecio {
        if ($this->productos->buscarPorId($productoId) === null) {
            throw new DomainException('El producto indicado no existe.');
        }

        $fechaInicio ??= new DateTimeImmutable;
        $nuevo = new ProductoPrecio(
            id: null,
            productoId: $productoId,
            tipoCliente: $tipoCliente,
            precio: $precio,
            fechaInicio: $fechaInicio,
            fechaFin: null,
            estado: 'ACTIVO',
            creadoPorUserId: $creadoPorUserId,
            cantidadMinima: $cantidadMinima,
        );

        $this->precios->cerrarVigentes($productoId, $tipoCliente, $fechaInicio);

        return $this->precios->guardar($nuevo);
    }
}
