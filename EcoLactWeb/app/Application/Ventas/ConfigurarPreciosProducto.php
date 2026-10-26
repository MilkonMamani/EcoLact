<?php

namespace App\Application\Ventas;

use App\Application\Shared\TransactionManager;
use App\Domain\Produccion\ProductoRepository;
use App\Domain\Ventas\ProductoPrecioRepository;
use DateTimeImmutable;
use DomainException;

/**
 * INVENTARIO -> Productos: el administrador fija de una vez los tres precios
 * de un producto (proveedor, público, mayorista) y desde cuántas unidades
 * aplica el mayorista. Un precio que no cambia no se toca (no genera una
 * versión nueva); uno que se deja vacío se retira (deja de estar vigente).
 * Ventas lee estos precios con ProductoPrecioRepository::buscarVigente().
 */
final class ConfigurarPreciosProducto
{
    public function __construct(
        private readonly ProductoRepository $productos,
        private readonly ProductoPrecioRepository $precios,
        private readonly RegistrarProductoPrecio $registrarPrecio,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(
        int $productoId,
        ?float $precioProveedor,
        ?float $precioPublico,
        ?float $precioMayorista,
        ?float $cantidadMinimaMayorista,
        int $creadoPorUserId,
    ): void {
        if ($this->productos->buscarPorId($productoId) === null) {
            throw new DomainException('El producto indicado no existe.');
        }

        if ($cantidadMinimaMayorista !== null && $precioMayorista === null) {
            throw new DomainException('Indica el precio mayorista para usar una cantidad mínima.');
        }

        $nuevos = [
            'PROVEEDOR' => [$precioProveedor, null],
            'PUBLICO' => [$precioPublico, null],
            'MAYORISTA' => [$precioMayorista, $cantidadMinimaMayorista],
        ];

        $this->transacciones->run(function () use ($productoId, $nuevos, $creadoPorUserId): void {
            $hoy = new DateTimeImmutable;

            foreach ($nuevos as $tipoCliente => [$precio, $cantidadMinima]) {
                if ($precio === null) {
                    $this->precios->cerrarVigentes($productoId, $tipoCliente, $hoy);

                    continue;
                }

                $actual = $this->precios->buscarVigente($productoId, $tipoCliente, $hoy);

                if ($actual !== null && abs($actual->precio() - $precio) < 0.005 && $actual->cantidadMinima() === $cantidadMinima) {
                    continue;
                }

                $this->registrarPrecio->execute(
                    productoId: $productoId,
                    tipoCliente: $tipoCliente,
                    precio: $precio,
                    creadoPorUserId: $creadoPorUserId,
                    fechaInicio: $hoy,
                    cantidadMinima: $cantidadMinima,
                );
            }
        });
    }

    /**
     * "Eliminar precio": el producto queda sin precios vigentes (no se podrá
     * vender hasta configurarlos de nuevo). El historial no se borra.
     */
    public function retirarTodos(int $productoId): void
    {
        $hoy = new DateTimeImmutable;

        $this->transacciones->run(function () use ($productoId, $hoy): void {
            foreach (['PROVEEDOR', 'PUBLICO', 'MAYORISTA'] as $tipoCliente) {
                $this->precios->cerrarVigentes($productoId, $tipoCliente, $hoy);
            }
        });
    }
}
