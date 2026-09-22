<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Produccion\InsumoRepository;
use App\Domain\Shared\Enums\MotivoMovimientoInsumo;
use DomainException;

/**
 * Registra el ingreso de stock de un insumo por compra (sal, azúcar, cuajo,
 * envases...). La cantidad puede indicarse en cualquier unidad compatible con
 * la unidad base del insumo (por ejemplo, 50 kg de azúcar que se controla en
 * g): se convierte y se guarda en la unidad base.
 *
 * La leche no se repone por compra sino por las entregas de los proveedores.
 */
final class RegistrarCompraInsumo
{
    public function __construct(
        private readonly RegistrarMovimientoInsumo $registrarMovimiento,
        private readonly InsumoRepository $insumos,
    ) {}

    public function execute(
        int $insumoId,
        float $cantidad,
        int $registradoPorUserId,
        ?string $observacion = null,
        ?string $unidad = null,
    ): InsumoMovimiento {
        if ($this->insumos->buscarPorId($insumoId)?->esLeche()) {
            throw new DomainException('La leche no se repone por compra: su stock aumenta automáticamente con las entregas de los proveedores.');
        }

        return $this->registrarMovimiento->execute(
            insumoId: $insumoId,
            motivo: MotivoMovimientoInsumo::INGRESO_COMPRA,
            direccion: MotivoMovimientoInsumo::ENTRADA,
            cantidad: $cantidad,
            registradoPorUserId: $registradoPorUserId,
            observacion: $observacion,
            unidad: $unidad,
        );
    }
}
