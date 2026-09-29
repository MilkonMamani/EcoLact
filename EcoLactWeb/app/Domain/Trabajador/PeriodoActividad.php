<?php

namespace App\Domain\Trabajador;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Tramo en el que un trabajador estuvo ACTIVO: [inicio, fin] en días
 * calendario, ambos inclusive; fin null = sigue activo. Fuera de sus tramos
 * un trabajador "no corresponde": no marca asistencia, no recibe faltas y no
 * acumula pago.
 */
final readonly class PeriodoActividad
{
    public DateTimeImmutable $inicio;

    public ?DateTimeImmutable $fin;

    public function __construct(DateTimeImmutable $inicio, ?DateTimeImmutable $fin = null)
    {
        $this->inicio = self::dia($inicio);
        $this->fin = $fin !== null ? self::dia($fin) : null;

        if ($this->fin !== null && $this->fin < $this->inicio) {
            throw new InvalidArgumentException('El fin de un periodo de actividad no puede ser anterior a su inicio.');
        }
    }

    public function estaAbierto(): bool
    {
        return $this->fin === null;
    }

    public function contiene(DateTimeImmutable $fecha): bool
    {
        $dia = self::dia($fecha);

        return $this->inicio <= $dia && ($this->fin === null || $this->fin >= $dia);
    }

    /** Días del rango [inicio, fin] que caen dentro de este periodo. */
    public function diasEn(DateTimeImmutable $inicio, DateTimeImmutable $fin): int
    {
        $desde = max(self::dia($inicio), $this->inicio);
        $hasta = $this->fin !== null ? min(self::dia($fin), $this->fin) : self::dia($fin);

        return $desde > $hasta ? 0 : (int) $desde->diff($hasta)->days + 1;
    }

    /**
     * Día calendario sin hora ni zona horaria: las fechas llegan de la BD (UTC)
     * y del reloj del negocio (America/Lima); se comparan por su fecha, no por el instante.
     */
    private static function dia(DateTimeImmutable $fecha): DateTimeImmutable
    {
        return new DateTimeImmutable($fecha->format('Y-m-d'), new \DateTimeZone('UTC'));
    }
}
