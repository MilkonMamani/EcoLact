<?php

namespace App\Domain\Auth;

use InvalidArgumentException;

final class Role
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $nombre,
        private readonly ?string $descripcion,
        private readonly bool $esRemunerable = false,
    ) {
        if (trim($nombre) === '') {
            throw new InvalidArgumentException('El nombre del rol es obligatorio.');
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

    public function esRemunerable(): bool
    {
        return $this->esRemunerable;
    }
}
