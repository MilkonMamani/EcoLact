<?php

namespace App\Domain\Produccion;

use App\Domain\Shared\Enums\TipoInsumo;
use InvalidArgumentException;

/**
 * Catálogo de insumos y materias primas. `stockActual` está SIEMPRE en la
 * unidad base del insumo; es un saldo cacheado cuya fuente de verdad son los
 * movimientos (insumo_movimientos). `codigo` identifica a los insumos del
 * sistema (hoy solo LECHE) sin depender del nombre.
 */
final class Insumo
{
    public const CODIGO_LECHE = 'LECHE';

    public function __construct(
        private readonly ?int $id,
        private readonly string $nombre,
        private readonly string $unidadBase,
        private readonly float $stockActual,
        private readonly float $stockMinimo,
        private readonly string $estado,
        private readonly TipoInsumo $tipo = TipoInsumo::INSUMO,
        private readonly ?string $codigo = null,
        private readonly ?string $descripcion = null,
    ) {
        if (trim($nombre) === '') {
            throw new InvalidArgumentException('El nombre del insumo es obligatorio.');
        }

        if (! UnidadMedida::esValida($unidadBase)) {
            throw new InvalidArgumentException('La unidad base del insumo no es válida. Use: '.implode(', ', UnidadMedida::codigos()).'.');
        }

        if ($stockActual < 0.0) {
            throw new InvalidArgumentException('El stock actual del insumo no puede ser negativo.');
        }

        if ($stockMinimo < 0.0) {
            throw new InvalidArgumentException('El stock mínimo del insumo no puede ser negativo.');
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

    public function unidadBase(): string
    {
        return $this->unidadBase;
    }

    public function stockActual(): float
    {
        return $this->stockActual;
    }

    public function stockMinimo(): float
    {
        return $this->stockMinimo;
    }

    public function estado(): string
    {
        return $this->estado;
    }

    public function tipo(): TipoInsumo
    {
        return $this->tipo;
    }

    public function codigo(): ?string
    {
        return $this->codigo;
    }

    public function descripcion(): ?string
    {
        return $this->descripcion;
    }

    public function esLeche(): bool
    {
        return $this->codigo === self::CODIGO_LECHE;
    }

    /**
     * Los insumos con código los necesita el sistema (la leche recibe las
     * entregas): no se pueden desactivar ni cambiar su tipo, unidad o código.
     */
    public function esDelSistema(): bool
    {
        return $this->codigo !== null;
    }

    public function estaBajoMinimo(): bool
    {
        return $this->stockMinimo > 0.0 && $this->stockActual <= $this->stockMinimo;
    }

    public function conStock(float $nuevoStock): self
    {
        return new self(
            id: $this->id,
            nombre: $this->nombre,
            unidadBase: $this->unidadBase,
            stockActual: round($nuevoStock, 4),
            stockMinimo: $this->stockMinimo,
            estado: $this->estado,
            tipo: $this->tipo,
            codigo: $this->codigo,
            descripcion: $this->descripcion,
        );
    }
}
