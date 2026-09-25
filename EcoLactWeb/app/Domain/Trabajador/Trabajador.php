<?php

namespace App\Domain\Trabajador;

use App\Domain\Shared\Enums\EstadoUsuario;
use DateTimeImmutable;

/**
 * Identidad laboral de una persona del personal remunerable: proyección
 * derivada de users + rol + ficha (o solo users para roles sin ficha, hoy
 * ADMINISTRADOR), sincronizada por SincronizarTrabajadores. Es el ancla de
 * asistencia (jornadas_personal), QR y nómina.
 */
final class Trabajador
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $userId,
        private readonly ?string $dni,
        private readonly string $nombres,
        private readonly string $apellidos,
        private readonly ?string $telefono,
        private readonly EstadoUsuario $estado,
        private readonly DateTimeImmutable $fechaIngreso,
        private readonly ?DateTimeImmutable $fechaSalida,
        private readonly ?string $qrToken,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function dni(): ?string
    {
        return $this->dni;
    }

    public function nombres(): string
    {
        return $this->nombres;
    }

    public function apellidos(): string
    {
        return $this->apellidos;
    }

    public function nombreCompleto(): string
    {
        return trim($this->nombres.' '.$this->apellidos);
    }

    public function telefono(): ?string
    {
        return $this->telefono;
    }

    public function estado(): EstadoUsuario
    {
        return $this->estado;
    }

    public function fechaIngreso(): DateTimeImmutable
    {
        return $this->fechaIngreso;
    }

    public function fechaSalida(): ?DateTimeImmutable
    {
        return $this->fechaSalida;
    }

    public function qrToken(): ?string
    {
        return $this->qrToken;
    }

    public function estaActivo(): bool
    {
        return $this->estado === EstadoUsuario::ACTIVO;
    }

    /** Vigente esa fecha: ya ingresó y (si tiene fecha de salida) todavía no salió. */
    public function estaVigenteEn(DateTimeImmutable $fecha): bool
    {
        $dia = $fecha->setTime(0, 0);

        return $this->fechaIngreso->setTime(0, 0) <= $dia
            && ($this->fechaSalida === null || $this->fechaSalida->setTime(0, 0) >= $dia);
    }

    /** Estuvo vigente durante TODO el rango [inicio, fin] (p.ej. un mes calendario completo). */
    public function estuvoVigenteTodoElRango(DateTimeImmutable $inicio, DateTimeImmutable $fin): bool
    {
        return $this->fechaIngreso->setTime(0, 0) <= $inicio->setTime(0, 0)
            && ($this->fechaSalida === null || $this->fechaSalida->setTime(0, 0) >= $fin->setTime(0, 0));
    }
}
