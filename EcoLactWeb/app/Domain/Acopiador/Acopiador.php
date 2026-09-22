<?php

namespace App\Domain\Acopiador;

use App\Domain\Shared\Enums\EstadoUsuario;
use DateTimeImmutable;
use InvalidArgumentException;

final class Acopiador
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $userId,
        private readonly int $zonaId,
        private readonly string $dni,
        private readonly string $nombres,
        private readonly string $apellidos,
        private readonly ?string $telefono,
        private readonly EstadoUsuario $estado,
        private readonly ?DateTimeImmutable $fechaIngreso = null,
    ) {
        if (trim($dni) === '') {
            throw new InvalidArgumentException('El DNI del acopiador es obligatorio.');
        }

        if (trim($nombres) === '' || trim($apellidos) === '') {
            throw new InvalidArgumentException('El nombre y apellido del acopiador son obligatorios.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function zonaId(): int
    {
        return $this->zonaId;
    }

    public function dni(): string
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

    public function telefono(): ?string
    {
        return $this->telefono;
    }

    public function estado(): EstadoUsuario
    {
        return $this->estado;
    }

    public function fechaIngreso(): ?DateTimeImmutable
    {
        return $this->fechaIngreso;
    }

    public function nombreCompleto(): string
    {
        return trim("{$this->nombres} {$this->apellidos}");
    }
}
