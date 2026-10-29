<?php

namespace App\Domain\Compra;

use App\Domain\Shared\Enums\CategoriaMovimientoCaja;
use App\Domain\Shared\Enums\TratamientoCompra;

/**
 * Tipo de adquisición (Insumos de producción, Materiales de limpieza...).
 * Catálogo de la tabla categorias_compra: se extiende con filas nuevas.
 */
final class CategoriaCompra
{
    public const INSUMOS_PRODUCCION = 'INSUMOS_PRODUCCION';

    public function __construct(
        private readonly int $id,
        private readonly string $codigo,
        private readonly string $nombre,
        private readonly ?string $descripcion,
        private readonly TratamientoCompra $tratamientoSugerido,
        private readonly CategoriaMovimientoCaja $categoriaCaja,
        private readonly bool $activa,
    ) {}

    public function id(): int
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

    public function descripcion(): ?string
    {
        return $this->descripcion;
    }

    public function tratamientoSugerido(): TratamientoCompra
    {
        return $this->tratamientoSugerido;
    }

    /** INSUMOS o GASTOS: categoría de Caja de sus pagos. */
    public function categoriaCaja(): CategoriaMovimientoCaja
    {
        return $this->categoriaCaja;
    }

    public function activa(): bool
    {
        return $this->activa;
    }
}
