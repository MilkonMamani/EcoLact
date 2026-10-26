<?php

namespace App\Domain\Produccion;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Compra física de insumos, desde una boleta escaneada (BOLETA) o registrada
 * a mano (MANUAL). Los datos del comprobante son opcionales. El usuario que
 * la registra es quien la CONFIRMÓ en EcoLact, no el proveedor comercial.
 */
final class CompraInsumo
{
    public const ORIGEN_BOLETA = 'BOLETA';

    public const ORIGEN_MANUAL = 'MANUAL';

    public function __construct(
        private readonly ?int $id,
        private readonly string $origen,
        private readonly DateTimeImmutable $fecha,
        private readonly int $registradoPorUserId,
        private readonly ?string $proveedorComercial = null,
        private readonly ?string $ruc = null,
        private readonly ?string $numeroComprobante = null,
        private readonly ?float $total = null,
        private readonly ?string $imagenBoleta = null,
        private readonly ?string $observacion = null,
        private readonly ?DateTimeImmutable $registradoEn = null,
    ) {
        if (! in_array($origen, [self::ORIGEN_BOLETA, self::ORIGEN_MANUAL], true)) {
            throw new InvalidArgumentException('El origen de la compra debe ser BOLETA o MANUAL.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function origen(): string
    {
        return $this->origen;
    }

    public function fecha(): DateTimeImmutable
    {
        return $this->fecha;
    }

    public function registradoPorUserId(): int
    {
        return $this->registradoPorUserId;
    }

    public function proveedorComercial(): ?string
    {
        return $this->proveedorComercial;
    }

    public function ruc(): ?string
    {
        return $this->ruc;
    }

    public function numeroComprobante(): ?string
    {
        return $this->numeroComprobante;
    }

    public function total(): ?float
    {
        return $this->total;
    }

    public function imagenBoleta(): ?string
    {
        return $this->imagenBoleta;
    }

    public function observacion(): ?string
    {
        return $this->observacion;
    }

    public function registradoEn(): ?DateTimeImmutable
    {
        return $this->registradoEn;
    }

    /**
     * Referencia corta para movimientos e historial: "Boleta B001-203" o "Compra #58".
     */
    public function referencia(): string
    {
        return $this->numeroComprobante !== null ? 'Boleta '.$this->numeroComprobante : 'Compra #'.$this->id;
    }
}
