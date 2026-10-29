<?php

namespace App\Domain\Produccion;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Lote de producción. La leche (y demás insumos) que consumió NO es un dato
 * del lote: se deriva de sus consumos (lote_produccion_insumos).
 */
final class LoteProduccion
{
    public const ESTADO_EN_PROCESO = 'EN_PROCESO';

    public const ESTADO_FINALIZADO = 'FINALIZADO';

    public function __construct(
        private readonly ?int $id,
        private readonly string $codigo,
        private readonly DateTimeImmutable $fechaHoraInicio,
        private readonly ?DateTimeImmutable $fechaHoraFin,
        private readonly string $estado,
        private readonly int $registradoPorUserId,
        private readonly ?string $observacion = null,
        private readonly ?string $observacionCierre = null,
        private readonly ?int $planificacionProduccionId = null,
    ) {
        if (trim($codigo) === '') {
            throw new InvalidArgumentException('El código del lote de producción es obligatorio.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function codigo(): string
    {
        return $this->codigo;
    }

    public function fechaHoraInicio(): DateTimeImmutable
    {
        return $this->fechaHoraInicio;
    }

    public function fechaHoraFin(): ?DateTimeImmutable
    {
        return $this->fechaHoraFin;
    }

    public function estado(): string
    {
        return $this->estado;
    }

    public function estaEnProceso(): bool
    {
        return $this->estado === self::ESTADO_EN_PROCESO;
    }

    public function registradoPorUserId(): int
    {
        return $this->registradoPorUserId;
    }

    public function observacion(): ?string
    {
        return $this->observacion;
    }

    /**
     * Planificación con la que se inició junto a otros lotes (null en lotes anteriores o de la API).
     */
    public function planificacionProduccionId(): ?int
    {
        return $this->planificacionProduccionId;
    }

    /**
     * Nota opcional registrada al finalizar el lote.
     */
    public function observacionCierre(): ?string
    {
        return $this->observacionCierre;
    }
}
