<?php

namespace App\Domain\Caja;

use App\Domain\Shared\Enums\CategoriaMovimientoCaja;
use App\Domain\Shared\Enums\TipoMovimientoCaja;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Dinero real que entró o salió de la caja. Nace SOLO de una operación
 * confirmada de otro módulo (venta, pago, compra) y es un registro de
 * trazabilidad: no tiene operaciones de edición.
 *
 * `referenciaTipo` + `referenciaId` identifican la operación de origen; un
 * mismo evento no puede generar dos movimientos.
 */
final class MovimientoCaja
{
    public const REFERENCIA_VENTA = 'VENTA';

    public const REFERENCIA_PAGO_PROVEEDOR = 'PAGO_PROVEEDOR';

    public const REFERENCIA_PAGO_TRABAJADOR = 'PAGO_TRABAJADOR';

    public const REFERENCIA_COMPRA_INSUMOS = 'COMPRA_INSUMOS';

    /**
     * @param  array<string, string>  $detalle  datos extra del panel de detalle (etiqueta => valor)
     */
    public function __construct(
        private readonly ?int $id,
        private readonly CategoriaMovimientoCaja $categoria,
        private readonly float $monto,
        private readonly string $concepto,
        private readonly ?string $descripcion,
        private readonly string $referencia,
        private readonly array $detalle,
        private readonly string $referenciaTipo,
        private readonly int $referenciaId,
        private readonly int $registradoPorUserId,
        private readonly DateTimeImmutable $fecha,
    ) {
        if (round($monto, 2) <= 0.0) {
            throw new InvalidArgumentException('El monto de un movimiento de caja debe ser mayor a cero.');
        }

        if (! in_array($referenciaTipo, [self::REFERENCIA_VENTA, self::REFERENCIA_PAGO_PROVEEDOR, self::REFERENCIA_PAGO_TRABAJADOR, self::REFERENCIA_COMPRA_INSUMOS], true)) {
            throw new InvalidArgumentException('El origen del movimiento de caja no es válido.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    /** El sentido lo fija la categoría: solo las ventas entran. */
    public function tipo(): TipoMovimientoCaja
    {
        return $this->categoria->tipo();
    }

    public function categoria(): CategoriaMovimientoCaja
    {
        return $this->categoria;
    }

    public function monto(): float
    {
        return round($this->monto, 2);
    }

    public function concepto(): string
    {
        return $this->concepto;
    }

    public function descripcion(): ?string
    {
        return $this->descripcion;
    }

    public function referencia(): string
    {
        return $this->referencia;
    }

    /**
     * @return array<string, string>
     */
    public function detalle(): array
    {
        return $this->detalle;
    }

    public function referenciaTipo(): string
    {
        return $this->referenciaTipo;
    }

    public function referenciaId(): int
    {
        return $this->referenciaId;
    }

    public function registradoPorUserId(): int
    {
        return $this->registradoPorUserId;
    }

    public function fecha(): DateTimeImmutable
    {
        return $this->fecha;
    }
}
