<?php

namespace App\Domain\Pago;

use DateTimeImmutable;

/**
 * Un día trabajado de una persona del personal remunerable. La base de
 * cálculo del negocio (30 días = un sueldo mensual) es una regla fija, no
 * configurable por rol, por eso vive aquí como constante de dominio y no en
 * ConfiguracionPago.
 */
final class JornadaPersonal
{
    public const DIAS_BASE_PERIODO = 30;

    public function __construct(
        private readonly ?int $id,
        private readonly int $userId,
        private readonly int $rolId,
        private readonly DateTimeImmutable $fecha,
        private readonly ?string $observacion,
        private readonly ?int $registradoPorUserId,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function rolId(): int
    {
        return $this->rolId;
    }

    public function fecha(): DateTimeImmutable
    {
        return $this->fecha;
    }

    public function observacion(): ?string
    {
        return $this->observacion;
    }

    public function registradoPorUserId(): ?int
    {
        return $this->registradoPorUserId;
    }
}
