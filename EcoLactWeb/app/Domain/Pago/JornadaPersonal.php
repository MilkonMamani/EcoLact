<?php

namespace App\Domain\Pago;

use App\Domain\Shared\Enums\EstadoJornada;
use App\Domain\Shared\Enums\OrigenJornada;
use DateTimeImmutable;

/**
 * Un día de asistencia de un trabajador del personal remunerable: PRESENTE
 * (marcado por QR, a mano por un administrador, o corregido) o FALTA
 * (generada automáticamente a las 12:05 si no hubo marcación, o corregida a
 * mano). A diferencia del modelo anterior, la ausencia de fila para una
 * fecha NO significa nada por sí sola: solo se crean filas para fechas
 * dentro del rango laboral del trabajador (desde su fecha_ingreso).
 */
final class JornadaPersonal
{
    /** Divisor fijo del sueldo mensual (regla de negocio, no configurable por rol). */
    public const DIAS_BASE_PERIODO = 30;

    public function __construct(
        private readonly ?int $id,
        private readonly int $trabajadorId,
        private readonly int $rolId,
        private readonly DateTimeImmutable $fecha,
        private readonly EstadoJornada $estado,
        private readonly OrigenJornada $origen,
        private readonly ?DateTimeImmutable $horaMarcacion,
        private readonly ?string $observacion,
        private readonly ?int $registradoPorUserId,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function trabajadorId(): int
    {
        return $this->trabajadorId;
    }

    public function rolId(): int
    {
        return $this->rolId;
    }

    public function fecha(): DateTimeImmutable
    {
        return $this->fecha;
    }

    public function estado(): EstadoJornada
    {
        return $this->estado;
    }

    public function origen(): OrigenJornada
    {
        return $this->origen;
    }

    public function horaMarcacion(): ?DateTimeImmutable
    {
        return $this->horaMarcacion;
    }

    public function observacion(): ?string
    {
        return $this->observacion;
    }

    public function registradoPorUserId(): ?int
    {
        return $this->registradoPorUserId;
    }

    public function estaPresente(): bool
    {
        return $this->estado === EstadoJornada::PRESENTE;
    }
}
