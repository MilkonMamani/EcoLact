<?php

namespace App\Application\Produccion;

use App\Application\Shared\TransactionManager;
use App\Domain\Produccion\ProductoMovimiento;
use App\Domain\Produccion\ProductoMovimientoRepository;
use App\Domain\Produccion\ProductoRepository;
use App\Domain\Produccion\ProductoStockRepository;
use DateTimeImmutable;
use DomainException;

/**
 * Corrige una diferencia real de inventario de un producto terminado
 * (conteo físico distinto al registrado). No debe usarse para registrar
 * producción ni ventas: esas operaciones ya generan sus propios movimientos
 * (FinalizarProduccion / RegistrarVentaProducto).
 */
final class RegistrarAjusteProducto
{
    public function __construct(
        private readonly ProductoRepository $productos,
        private readonly ProductoStockRepository $stock,
        private readonly ProductoMovimientoRepository $movimientos,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(
        int $productoId,
        float $nuevoStock,
        string $motivo,
        int $registradoPorUserId,
        ?string $observacion = null,
    ): ProductoMovimiento {
        if ($nuevoStock < 0.0) {
            throw new DomainException('El nuevo stock no puede ser negativo.');
        }

        if ($this->productos->buscarPorId($productoId) === null) {
            throw new DomainException('El producto indicado no existe.');
        }

        return $this->transacciones->run(function () use (
            $productoId, $nuevoStock, $motivo, $registradoPorUserId, $observacion
        ): ProductoMovimiento {
            $this->stock->bloquearProducto($productoId);

            $diferencia = round($nuevoStock - ($this->stock->buscarPorProductoId($productoId)?->cantidadActual() ?? 0.0), 2);

            if ($diferencia === 0.0) {
                throw new DomainException('El nuevo stock es igual al stock actual: no hay nada que ajustar.');
            }

            return $this->movimientos->guardar(new ProductoMovimiento(
                id: null,
                productoId: $productoId,
                tipo: $diferencia > 0 ? 'AJUSTE_ENTRADA' : 'AJUSTE_SALIDA',
                cantidad: abs($diferencia),
                loteProduccionId: null,
                ventaProductoId: null,
                fecha: new DateTimeImmutable,
                registradoPorUserId: $registradoPorUserId,
                observacion: trim($motivo.($observacion ? ' — '.$observacion : '')),
            ));
        });
    }
}
