<?php

namespace App\Domain\Ventas;

use InvalidArgumentException;

/**
 * Agenda de clientes registrados: quién compró (nombre o razón social,
 * documento/RUC, teléfono). No define qué precio se le cobra.
 */
final class Comprador
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $nombre,
        private readonly ?string $documento,
        private readonly ?string $telefono,
        private readonly string $estado,
        private readonly ?string $tipoCliente = null,
    ) {
        if (trim($nombre) === '') {
            throw new InvalidArgumentException('El nombre del comprador es obligatorio.');
        }

        if ($tipoCliente !== null && ! in_array($tipoCliente, ['MAYORISTA', 'PUBLICO'], true)) {
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

    /**
     * Solo histórico (compradores anteriores a que la cantidad decidiera el
     * precio mayorista). NO se usa para resolver precios; los nuevos son null.
     */
    public function tipoCliente(): ?string
    {
        return $this->tipoCliente;
    }

    public function estado(): string
    {
        return $this->estado;
    }
}
