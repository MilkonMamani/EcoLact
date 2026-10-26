<?php

namespace App\Domain\Produccion;

use App\Domain\Shared\Enums\MotivoMovimientoInsumo;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Un movimiento de inventario de un insumo, siempre en su unidad base.
 * `tipo` es la dirección (ENTRADA/SALIDA); `motivo` la causa (ver
 * MotivoMovimientoInsumo). `origenTipo`/`origenId` indican qué registro lo
 * generó (por ejemplo ENTREGA #12 o LOTE_PRODUCCION #4).
 */
final class InsumoMovimiento
{
    public const ORIGEN_ENTREGA = 'ENTREGA';

    public const ORIGEN_LOTE_PRODUCCION = 'LOTE_PRODUCCION';

    public const ORIGEN_COMPRA_INSUMO = 'COMPRA_INSUMO';

    public function __construct(
        private readonly ?int $id,
        private readonly int $insumoId,
        private readonly string $tipo,
        private readonly string $motivo,
        private readonly float $cantidad,
        private readonly ?int $loteProduccionId,
        private readonly DateTimeImmutable $fecha,
        private readonly int $registradoPorUserId,
        private readonly ?string $observacion = null,
        private readonly ?string $origenTipo = null,
        private readonly ?int $origenId = null,
    ) {
        if (! in_array($tipo, [MotivoMovimientoInsumo::ENTRADA, MotivoMovimientoInsumo::SALIDA], true)) {
            throw new InvalidArgumentException('El tipo de movimiento de insumo debe ser ENTRADA o SALIDA.');
        }

        $motivoEnum = MotivoMovimientoInsumo::tryFrom($motivo)
            ?? throw new InvalidArgumentException("El motivo de movimiento '{$motivo}' no es válido.");

        $direccionFija = $motivoEnum->direccionFija();

        if ($direccionFija !== null && $direccionFija !== $tipo) {
            throw new InvalidArgumentException("El motivo {$motivo} solo admite movimientos de tipo {$direccionFija}.");
        }

        if ($cantidad <= 0.0) {
            throw new InvalidArgumentException('La cantidad del movimiento de insumo debe ser mayor a cero.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function insumoId(): int
    {
        return $this->insumoId;
    }

    public function tipo(): string
    {
        return $this->tipo;
    }

    public function motivo(): string
    {
        return $this->motivo;
    }

    public function motivoEtiqueta(): string
    {
        return MotivoMovimientoInsumo::from($this->motivo)->etiqueta();
    }

    public function cantidad(): float
    {
        return $this->cantidad;
    }

    public function loteProduccionId(): ?int
    {
        return $this->loteProduccionId;
    }

    public function fecha(): DateTimeImmutable
    {
        return $this->fecha;
    }

    public function registradoPorUserId(): int
    {
        return $this->registradoPorUserId;
    }

    public function observacion(): ?string
    {
        return $this->observacion;
    }

    public function origenTipo(): ?string
    {
        return $this->origenTipo;
    }

    public function origenId(): ?int
    {
        return $this->origenId;
    }

    public function esEntrada(): bool
    {
        return $this->tipo === MotivoMovimientoInsumo::ENTRADA;
    }

    /**
     * Cantidad con signo: positiva para ENTRADA, negativa para SALIDA.
     */
    public function cantidadConSigno(): float
    {
        return $this->esEntrada() ? $this->cantidad : -$this->cantidad;
    }
}
