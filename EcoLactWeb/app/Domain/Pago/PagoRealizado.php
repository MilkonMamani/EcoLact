<?php

namespace App\Domain\Pago;

use App\Domain\Shared\Enums\MetodoPago;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Pago real: el dinero ya fue entregado. Es un registro financiero, por eso
 * no tiene operaciones de edición.
 */
final class PagoRealizado
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $obligacionPagoId,
        private readonly DateTimeImmutable $fechaPago,
        private readonly float $monto,
        private readonly MetodoPago $metodoPago,
        private readonly ?string $numeroOperacion,
        private readonly ?string $observacion,
        private readonly ?string $comprobantePath,
        private readonly ?string $comprobanteNombre,
        private readonly int $registradoPorUserId,
        private readonly ?int $pagoProveedorId = null,
        private readonly ?DateTimeImmutable $pagadoAt = null,
        private readonly ?DateTimeImmutable $impresoAt = null,
        private readonly ?string $loteCodigo = null,
        private readonly ?array $comprobanteSnapshot = null,
    ) {
        if ($monto <= 0.0) {
            throw new InvalidArgumentException('El monto del pago debe ser mayor a cero.');
        }

        if ($metodoPago->requiereNumeroOperacion() && ($numeroOperacion === null || trim($numeroOperacion) === '')) {
            throw new InvalidArgumentException('El número de operación es obligatorio para el método de pago elegido.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function obligacionPagoId(): int
    {
        return $this->obligacionPagoId;
    }

    public function fechaPago(): DateTimeImmutable
    {
        return $this->fechaPago;
    }

    public function monto(): float
    {
        return $this->monto;
    }

    public function metodoPago(): MetodoPago
    {
        return $this->metodoPago;
    }

    public function numeroOperacion(): ?string
    {
        return $this->numeroOperacion;
    }

    public function observacion(): ?string
    {
        return $this->observacion;
    }

    public function comprobantePath(): ?string
    {
        return $this->comprobantePath;
    }

    public function comprobanteNombre(): ?string
    {
        return $this->comprobanteNombre;
    }

    public function registradoPorUserId(): int
    {
        return $this->registradoPorUserId;
    }

    public function pagoProveedorId(): ?int
    {
        return $this->pagoProveedorId;
    }

    /** Fecha y hora exactas en que se confirmó la entrega del dinero. */
    public function pagadoAt(): ?DateTimeImmutable
    {
        return $this->pagadoAt;
    }

    public function impresoAt(): ?DateTimeImmutable
    {
        return $this->impresoAt;
    }

    public function loteCodigo(): ?string
    {
        return $this->loteCodigo;
    }

    /**
     * @return array<string, mixed>|null copia de lo que se imprimió en el comprobante
     */
    public function comprobanteSnapshot(): ?array
    {
        return $this->comprobanteSnapshot;
    }
}
