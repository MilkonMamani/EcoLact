<?php

namespace App\Domain\Ventas;

use DateTimeImmutable;
use InvalidArgumentException;

final class VentaProducto
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $productoId,
        private readonly ?int $proveedorId,
        private readonly ?int $compradorId,
        private readonly string $tipoCliente,
        private readonly float $cantidad,
        private readonly float $precioUnitario,
        private readonly float $importeTotal,
        private readonly string $formaPago,
        private readonly ?int $periodoId,
        private readonly string $estado,
        private readonly DateTimeImmutable $fecha,
        private readonly int $registradoPorUserId,
    ) {
        if (! in_array($tipoCliente, ['PROVEEDOR', 'MAYORISTA', 'PUBLICO'], true)) {
            throw new InvalidArgumentException('El tipo de cliente debe ser PROVEEDOR, MAYORISTA o PUBLICO.');
        }

        if (! in_array($formaPago, ['CONTADO', 'DESCUENTO_PLANILLA'], true)) {
            throw new InvalidArgumentException('La forma de pago debe ser CONTADO o DESCUENTO_PLANILLA.');
        }

        if ($formaPago === 'DESCUENTO_PLANILLA' && ($tipoCliente !== 'PROVEEDOR' || $proveedorId === null)) {
            throw new InvalidArgumentException('El descuento en planilla solo aplica a ventas a un PROVEEDOR.');
        }

        if ($cantidad <= 0.0) {
            throw new InvalidArgumentException('La cantidad vendida debe ser mayor a cero.');
        }

        if ($precioUnitario <= 0.0) {
            throw new InvalidArgumentException('El precio unitario debe ser mayor a cero.');
        }
    }

    /** Código visible de una venta: "V-00018" (el mismo que cita Caja). */
    public static function codigo(int $id): string
    {
        return 'V-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function productoId(): int
    {
        return $this->productoId;
    }

    public function proveedorId(): ?int
    {
        return $this->proveedorId;
    }

    public function compradorId(): ?int
    {
        return $this->compradorId;
    }

    public function tipoCliente(): string
    {
        return $this->tipoCliente;
    }

    public function cantidad(): float
    {
        return $this->cantidad;
    }

    public function precioUnitario(): float
    {
        return $this->precioUnitario;
    }

    public function importeTotal(): float
    {
        return $this->importeTotal;
    }

    public function formaPago(): string
    {
        return $this->formaPago;
    }

    public function periodoId(): ?int
    {
        return $this->periodoId;
    }

    public function estado(): string
    {
        return $this->estado;
    }

    public function fecha(): DateTimeImmutable
    {
        return $this->fecha;
    }

    public function registradoPorUserId(): int
    {
        return $this->registradoPorUserId;
    }
}
