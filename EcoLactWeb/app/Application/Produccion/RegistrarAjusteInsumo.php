<?php

namespace App\Application\Produccion;

use App\Application\Shared\TransactionManager;
use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Produccion\InsumoRepository;
use App\Domain\Shared\Enums\MotivoMovimientoInsumo;
use DomainException;

/**
 * Corrige una diferencia real de inventario de un insumo (conteo físico
 * distinto al registrado). Se indica el stock que realmente hay
 * ($nuevoStock, en la unidad base) y se registra el movimiento AJUSTE_ENTRADA
 * o AJUSTE_SALIDA por la diferencia. No debe usarse para compras ni para el
 * consumo de producción: esas operaciones generan sus propios movimientos.
 */
final class RegistrarAjusteInsumo
{
    public function __construct(
        private readonly InsumoRepository $insumos,
        private readonly RegistrarMovimientoInsumo $registrarMovimiento,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(
        int $insumoId,
        float $nuevoStock,
        string $motivo,
        int $registradoPorUserId,
        ?string $observacion = null,
    ): InsumoMovimiento {
        if ($nuevoStock < 0.0) {
            throw new DomainException('El nuevo stock no puede ser negativo.');
        }

        return $this->transacciones->run(function () use ($insumoId, $nuevoStock, $motivo, $registradoPorUserId, $observacion): InsumoMovimiento {
            $insumo = $this->insumos->bloquearPorId($insumoId)
                ?? throw new DomainException('El insumo indicado no existe.');

            $diferencia = round($nuevoStock - $insumo->stockActual(), 4);

            if ($diferencia === 0.0) {
                throw new DomainException('El nuevo stock es igual al stock actual: no hay nada que ajustar.');
            }

            return $this->registrarMovimiento->execute(
                insumoId: $insumoId,
                motivo: $diferencia > 0 ? MotivoMovimientoInsumo::AJUSTE_ENTRADA : MotivoMovimientoInsumo::AJUSTE_SALIDA,
                direccion: $diferencia > 0 ? MotivoMovimientoInsumo::ENTRADA : MotivoMovimientoInsumo::SALIDA,
                cantidad: abs($diferencia),
                registradoPorUserId: $registradoPorUserId,
                observacion: trim($motivo.($observacion ? ' — '.$observacion : '')),
            );
        });
    }
}
