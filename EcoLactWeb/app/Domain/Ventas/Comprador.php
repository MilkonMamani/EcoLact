<?php

namespace App\Domain\Ventas;

use InvalidArgumentException;

final class Comprador
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $nombre,
        private readonly ?string $documento,
        private readonly ?string $telefono,
        private readonly string $tipoCliente,
        private readonly string $estado,
    ) {
        if (trim($nombre) === '') {
            throw new InvalidArgumentException('El nombre del comprador es obligatorio.');
        }

        if (! in_array($tipoCliente, ['MAYORISTA', 'PUBLICO'], true)) {
            throw new InvalidArgumentException('El tipo de cliente de un comprador debe ser MAYORISTA o PUBLICO.');
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

    public function documento(): ?string
    {
        return $this->documento;
    }

    public function telefono(): ?string
    {
        return $this->telefono;
    }

    public function tipoCliente(): string
    {
        return $this->tipoCliente;
    }

    public function estado(): string
    {
        return $this->estado;
    }
}
