<?php

namespace App\Domain\Calidad;

use InvalidArgumentException;

final class ParametroCalidad
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $codigo,
        private readonly string $nombre,
        private readonly ?string $unidad,
        private readonly string $estado,
        private readonly ?string $descripcion = null,
    ) {
        if (trim($codigo) === '') {
            throw new InvalidArgumentException('El código del parámetro de calidad es obligatorio.');
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

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function unidad(): ?string
    {
        return $this->unidad;
    }

    public function estado(): string
    {
        return $this->estado;
    }

    public function descripcion(): ?string
    {
        return $this->descripcion;
    }

    public function estaActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }
}
