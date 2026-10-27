<?php

namespace App\Application\Produccion;

use App\Application\Shared\TransactionManager;
use App\Domain\Produccion\Insumo;
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
 * La LECHE no admite ajustes de entrada: su única fuente es Acopio.
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

            return $this->registrar($insumo, $diferencia, $motivo, $registradoPorUserId, $observacion);
        });
    }

    /**
     * Ajuste expresado como entrada o salida de una cantidad ("ingresé 10 kg",
     * "se mermaron 2 kg"). La diferencia se aplica sobre el saldo leído con la
     * fila bloqueada: si otra operación movió el stock entre que la persona lo
     * vio y lo confirmó, no se pisa ese movimiento. Si la salida dejaría el
     * stock negativo, RegistrarMovimientoInsumo la rechaza.
     */
    public function porDiferencia(
        int $insumoId,
        string $direccion,
        float $cantidad,
        string $motivo,
        int $registradoPorUserId,
        ?string $observacion = null,
    ): InsumoMovimiento {
        if ($cantidad <= 0.0) {
            throw new DomainException('La cantidad debe ser mayor que cero.');
        }

        return $this->transacciones->run(function () use ($insumoId, $direccion, $cantidad, $motivo, $registradoPorUserId, $observacion): InsumoMovimiento {
            $insumo = $this->insumos->bloquearPorId($insumoId)
                ?? throw new DomainException('El insumo indicado no existe.');

            $diferencia = round($direccion === MotivoMovimientoInsumo::ENTRADA ? $cantidad : -$cantidad, 4);

            return $this->registrar($insumo, $diferencia, $motivo, $registradoPorUserId, $observacion);
        });
    }

    private function registrar(Insumo $insumo, float $diferencia, string $motivo, int $registradoPorUserId, ?string $observacion): InsumoMovimiento
    {
        // La leche solo ENTRA por Acopio (entregas aceptadas): un ajuste puede
        // registrar una pérdida, pero nunca sumar litros a mano.
        if ($insumo->esLeche() && $diferencia > 0) {
            throw new DomainException('La leche solo ingresa desde Acopio. Un ajuste solo puede registrar una pérdida de leche.');
        }

        return $this->registrarMovimiento->execute(
            insumoId: (int) $insumo->id(),
            motivo: $diferencia > 0 ? MotivoMovimientoInsumo::AJUSTE_ENTRADA : MotivoMovimientoInsumo::AJUSTE_SALIDA,
            direccion: $diferencia > 0 ? MotivoMovimientoInsumo::ENTRADA : MotivoMovimientoInsumo::SALIDA,
            cantidad: abs($diferencia),
            registradoPorUserId: $registradoPorUserId,
            observacion: trim($motivo.($observacion ? ' — '.$observacion : '')),
        );
    }
}
