<?php

namespace App\Domain\Produccion;

use InvalidArgumentException;

/**
 * Un producto terminado que EcoLact fabrica (Queso Paria 1 kg, Yogurt Fresa 1 L).
 * Nace desde su RECETA: el nombre completo se genera con el nombre base y la
 * presentación, y es único, de modo que varias versiones de receta comparten
 * siempre el mismo producto (y la misma imagen).
 */
final class Producto
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $nombre,
        private readonly string $unidadMedida,
        private readonly string $estado,
        private readonly ?string $nombreBase = null,
        private readonly ?string $tipoProducto = null,
        private readonly ?float $presentacionCantidad = null,
        private readonly ?string $presentacionUnidad = null,
        private readonly ?string $imagen = null,
        private readonly ?int $categoriaId = null,
    ) {
        if (trim($nombre) === '') {
            throw new InvalidArgumentException('El nombre del producto es obligatorio.');
        }

        if (trim($unidadMedida) === '') {
            throw new InvalidArgumentException('La unidad de medida del producto es obligatoria.');
        }

        if ($presentacionCantidad !== null && $presentacionCantidad <= 0.0) {
            throw new InvalidArgumentException('La presentación debe ser mayor que cero.');
        }

        if ($presentacionUnidad !== null && ! UnidadMedida::esValida($presentacionUnidad)) {
            throw new InvalidArgumentException('La unidad de la presentación no es válida.');
        }
    }

    /**
     * Nombre completo generado: "Queso Paria" + 1 + kg = "Queso Paria 1 kg".
     */
    public static function generarNombre(string $nombreBase, float $presentacionCantidad, string $presentacionUnidad): string
    {
        return trim(preg_replace('/\s+/', ' ', $nombreBase).' '.self::formatoCantidad($presentacionCantidad).' '.$presentacionUnidad);
    }

    public static function formatoCantidad(float $cantidad): string
    {
        return rtrim(rtrim(number_format($cantidad, 3, '.', ''), '0'), '.');
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function nombreBase(): string
    {
        return $this->nombreBase ?? $this->nombre;
    }

    public function tipoProducto(): ?string
    {
        return $this->tipoProducto;
    }

    public function categoriaId(): ?int
    {
        return $this->categoriaId;
    }

    public function presentacionCantidad(): ?float
    {
        return $this->presentacionCantidad;
    }

    public function presentacionUnidad(): ?string
    {
        return $this->presentacionUnidad;
    }

    /**
     * "1 kg", o null en productos históricos que aún no tienen presentación.
     */
    public function presentacion(): ?string
    {
        if ($this->presentacionCantidad === null || $this->presentacionUnidad === null) {
            return null;
        }

        return self::formatoCantidad($this->presentacionCantidad).' '.$this->presentacionUnidad;
    }

    /**
     * Nombre para elegir el producto en Producción: "Queso Paria · 1 kg". Los
     * productos históricos sin presentación muestran su nombre completo.
     */
    public function etiqueta(): string
    {
        $presentacion = $this->presentacion();

        return $presentacion === null ? $this->nombre : $this->nombreBase().' · '.$presentacion;
    }

    public function unidadMedida(): string
    {
        return $this->unidadMedida;
    }

    public function estado(): string
    {
        return $this->estado;
    }

    public function estaActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    /**
     * Ruta relativa de la imagen en el disco público (única copia), o null.
     */
    public function imagen(): ?string
    {
        return $this->imagen;
    }

    public function conImagen(?string $imagen): self
    {
        return new self(
            id: $this->id,
            nombre: $this->nombre,
            unidadMedida: $this->unidadMedida,
            estado: $this->estado,
            nombreBase: $this->nombreBase,
            tipoProducto: $this->tipoProducto,
            presentacionCantidad: $this->presentacionCantidad,
            presentacionUnidad: $this->presentacionUnidad,
            imagen: $imagen,
            categoriaId: $this->categoriaId,
        );
    }
}
