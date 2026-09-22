<?php

namespace App\Domain\Produccion;

use App\Domain\Shared\Enums\EstadoReceta;
use InvalidArgumentException;

/**
 * Fórmula de un producto: cuánto de cada insumo (leche incluida) se necesita
 * para obtener `rendimientoCantidad` del producto. Es solo configuración:
 * nunca mueve inventario. Las recetas se versionan; una versión ya usada por
 * una producción no se modifica.
 */
final class Receta
{
    /**
     * @param  list<RecetaDetalle>  $detalles
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $productoId,
        private readonly string $nombre,
        private readonly ?string $descripcion,
        private readonly float $rendimientoCantidad,
        private readonly string $rendimientoUnidad,
        private readonly int $version,
        private readonly EstadoReceta $estado,
        private readonly int $createdByUserId,
        private readonly array $detalles = [],
    ) {
        if (trim($nombre) === '') {
            throw new InvalidArgumentException('El nombre de la receta es obligatorio.');
        }

        if ($rendimientoCantidad <= 0.0) {
            throw new InvalidArgumentException('El rendimiento de la receta debe ser mayor que cero.');
        }

        if (trim($rendimientoUnidad) === '') {
            throw new InvalidArgumentException('La unidad del rendimiento es obligatoria.');
        }

        if ($version < 1) {
            throw new InvalidArgumentException('La versión de la receta debe ser 1 o mayor.');
        }
    }

    /**
     * El nombre de la receta NO se escribe a mano: se genera con el producto.
     */
    public static function generarNombre(string $nombreProducto): string
    {
        return 'Receta '.$nombreProducto;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function productoId(): int
    {
        return $this->productoId;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function descripcion(): ?string
    {
        return $this->descripcion;
    }

    public function rendimientoCantidad(): float
    {
        return $this->rendimientoCantidad;
    }

    public function rendimientoUnidad(): string
    {
        return $this->rendimientoUnidad;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function estado(): EstadoReceta
    {
        return $this->estado;
    }

    public function estaActiva(): bool
    {
        return $this->estado === EstadoReceta::ACTIVA;
    }

    public function createdByUserId(): int
    {
        return $this->createdByUserId;
    }

    /**
     * @return list<RecetaDetalle>
     */
    public function detalles(): array
    {
        return $this->detalles;
    }

    /**
     * Cuántas "recetas completas" hacen falta para fabricar la cantidad pedida.
     */
    public function factorPara(float $cantidadAProducir): float
    {
        return $cantidadAProducir / $this->rendimientoCantidad;
    }

    /**
     * @param  list<RecetaDetalle>|null  $detalles
     */
    public function con(
        ?string $nombre = null,
        ?string $descripcion = null,
        ?float $rendimientoCantidad = null,
        ?string $rendimientoUnidad = null,
        ?EstadoReceta $estado = null,
        ?array $detalles = null,
    ): self {
        return new self(
            id: $this->id,
            productoId: $this->productoId,
            nombre: $nombre ?? $this->nombre,
            descripcion: $descripcion ?? $this->descripcion,
            rendimientoCantidad: $rendimientoCantidad ?? $this->rendimientoCantidad,
            rendimientoUnidad: $rendimientoUnidad ?? $this->rendimientoUnidad,
            version: $this->version,
            estado: $estado ?? $this->estado,
            createdByUserId: $this->createdByUserId,
            detalles: $detalles ?? $this->detalles,
        );
    }
}
