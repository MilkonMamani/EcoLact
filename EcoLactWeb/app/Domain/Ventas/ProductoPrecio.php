<?php

namespace App\Domain\Ventas;

use DateTimeImmutable;
use InvalidArgumentException;

final class ProductoPrecio
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $productoId,
        private readonly string $tipoCliente,
        private readonly float $precio,
        private readonly DateTimeImmutable $fechaInicio,
        private readonly ?DateTimeImmutable $fechaFin,
        private readonly string $estado,
        private readonly int $creadoPorUserId,
        private readonly ?float $cantidadMinima = null,
    ) {
        if (! in_array($tipoCliente, ['PROVEEDOR', 'MAYORISTA', 'PUBLICO'], true)) {
            throw new InvalidArgumentException('El tipo de cliente debe ser PROVEEDOR, MAYORISTA o PUBLICO.');
        }

        if ($precio <= 0.0) {
            throw new InvalidArgumentException('El precio debe ser mayor a cero.');
        }

        if ($cantidadMinima !== null && ($tipoCliente !== 'MAYORISTA' || $cantidadMinima <= 0.0)) {
            throw new InvalidArgumentException('La cantidad mínima solo aplica al precio mayorista y debe ser mayor a cero.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function productoId(): int
    {
        return $this->productoId;
    }

    public function tipoCliente(): string
    {
        return $this->tipoCliente;
    }

    public function precio(): float
    {
        return $this->precio;
    }

    /**
     * Solo MAYORISTA: desde cuántas unidades compradas se aplica este precio
     * (TarifasProducto). Sin umbral (null, filas históricas) no se aplica nunca.
     */
    public function cantidadMinima(): ?float
    {
        return $this->cantidadMinima;
    }

    public function fechaInicio(): DateTimeImmutable
    {
        return $this->fechaInicio;
    }

    public function fechaFin(): ?DateTimeImmutable
    {
        return $this->fechaFin;
    }

    public function estado(): string
    {
        return $this->estado;
    }

    public function creadoPorUserId(): int
    {
        return $this->creadoPorUserId;
    }

    public function estaVigente(DateTimeImmutable $fecha): bool
    {
        if ($this->estado !== 'ACTIVO') {
            return false;
        }

        if ($this->fechaInicio > $fecha) {
            return false;
        }

        return $this->fechaFin === null || $this->fechaFin >= $fecha;
    }
}
