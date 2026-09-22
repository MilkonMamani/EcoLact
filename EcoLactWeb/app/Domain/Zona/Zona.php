<?php

namespace App\Domain\Zona;

use InvalidArgumentException;

final class Zona
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $nombre,
        private readonly ?string $descripcion,
        private readonly string $estado,
    ) {
        if (trim($nombre) === '') {
            throw new InvalidArgumentException('El nombre de la zona es obligatorio.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function descripcion(): ?string
    {
        return $this->descripcion;
    }

    public function estado(): string
    {
        return $this->estado;
    }

    public function estaActiva(): bool
    {
        return $this->estado === 'ACTIVO';
    }
}
