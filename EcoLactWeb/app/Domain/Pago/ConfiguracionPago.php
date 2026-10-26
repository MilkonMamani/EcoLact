<?php

namespace App\Domain\Pago;

use App\Domain\Shared\Enums\PeriodicidadPago;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Domain\Shared\Enums\TipoCalculoPago;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Define cuánto y cómo se paga, con vigencia histórica. Un proveedor cobra
 * por litros (el precio sale SIEMPRE de precios_leche, por eso monto_base es
 * nulo); el personal cobra un sueldo fijo mensual POR TRABAJADOR (userId), con
 * el rol real del trabajador. Las configuraciones por rol (userId nulo) son
 * historial del modelo anterior y ya no se usan para calcular sueldos.
 */
final class ConfiguracionPago
{
    public function __construct(
        private readonly ?int $id,
        private readonly TipoBeneficiarioPago $tipoBeneficiario,
        private readonly ?int $rolId,
        private readonly TipoCalculoPago $tipoCalculo,
        private readonly ?float $montoBase,
        private readonly PeriodicidadPago $periodicidad,
        private readonly bool $aplicaCalidad,
        private readonly DateTimeImmutable $fechaInicio,
        private readonly ?DateTimeImmutable $fechaFin,
        private readonly string $estado,
        private readonly ?string $observacion,
        private readonly int $creadoPorUserId,
        private readonly ?int $userId = null,
    ) {
        if ($fechaFin !== null && $fechaFin < $fechaInicio) {
            throw new InvalidArgumentException('La fecha fin de la configuración no puede ser anterior a la fecha de inicio.');
        }

        if ($tipoBeneficiario === TipoBeneficiarioPago::PROVEEDOR) {
            if ($tipoCalculo !== TipoCalculoPago::POR_LITROS || $rolId !== null || $userId !== null || $montoBase !== null || $periodicidad !== PeriodicidadPago::SEMANAL) {
                throw new InvalidArgumentException('Un proveedor se paga por litros con periodicidad semanal, sin rol y sin monto propio: el precio sale de Precio de leche.');
            }
        } else {
            if ($tipoCalculo !== TipoCalculoPago::SUELDO_FIJO || $periodicidad !== PeriodicidadPago::MENSUAL || $rolId === null) {
                throw new InvalidArgumentException('El personal se paga con sueldo fijo mensual asociado a un rol.');
            }

            if ($montoBase === null || $montoBase <= 0.0) {
                throw new InvalidArgumentException('El sueldo mensual debe ser mayor a cero.');
            }
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tipoBeneficiario(): TipoBeneficiarioPago
    {
        return $this->tipoBeneficiario;
    }

    public function rolId(): ?int
    {
        return $this->rolId;
    }

    /** Trabajador dueño del sueldo (null: proveedor o configuración histórica por rol). */
    public function userId(): ?int
    {
        return $this->userId;
    }

    public function tipoCalculo(): TipoCalculoPago
    {
        return $this->tipoCalculo;
    }

    public function montoBase(): ?float
    {
        return $this->montoBase;
    }

    public function periodicidad(): PeriodicidadPago
    {
        return $this->periodicidad;
    }

    public function aplicaCalidad(): bool
    {
        return $this->aplicaCalidad;
    }

    public function fechaInicio(): DateTimeImmutable
    {
        return $this->fechaInicio;
    }

    public function fechaFin(): ?DateTimeImmutable
    {
        return $this->fechaFin;
    }

    public function estado(): string
    {
        return $this->estado;
    }

    public function observacion(): ?string
    {
        return $this->observacion;
    }

    public function creadoPorUserId(): int
    {
        return $this->creadoPorUserId;
    }

    public function estaVigente(DateTimeImmutable $fecha): bool
    {
        if ($this->estado !== 'ACTIVO') {
            return false;
        }

        $dia = $fecha->setTime(0, 0);

        return $this->fechaInicio <= $dia && ($this->fechaFin === null || $this->fechaFin >= $dia);
    }

    public function conVigenciaHasta(?DateTimeImmutable $fechaFin): self
    {
        return new self(
            $this->id, $this->tipoBeneficiario, $this->rolId, $this->tipoCalculo, $this->montoBase,
            $this->periodicidad, $this->aplicaCalidad, $this->fechaInicio, $fechaFin, $this->estado,
            $this->observacion, $this->creadoPorUserId, $this->userId,
        );
    }
}
