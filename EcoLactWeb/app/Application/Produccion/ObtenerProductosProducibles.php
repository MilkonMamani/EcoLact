<?php

namespace App\Application\Produccion;

use App\Application\Produccion\Contracts\AlmacenImagenProducto;
use App\Domain\Produccion\Producto;
use App\Domain\Produccion\ProductoRepository;

/**
 * Productos que se pueden elegir en "Nueva producción": activos y con una
 * receta ACTIVA. Incluye la URL de su imagen (la misma que se subió en la receta).
 */
final class ObtenerProductosProducibles
{
    public function __construct(
        private readonly ProductoRepository $productos,
        private readonly AlmacenImagenProducto $imagenes,
    ) {}

    /**
     * @return list<array{id: int, nombre: string, etiqueta: string, unidad: string, imagen: ?string}>
     */
    public function execute(): array
    {
        return array_map(fn (Producto $p): array => [
            'id' => $p->id(),
            'nombre' => $p->nombre(),
            'etiqueta' => $p->etiqueta(),
            'unidad' => $p->unidadMedida(),
            'imagen' => $this->imagenes->url($p->imagen()),
        ], $this->productos->listarConRecetaActiva());
    }
}
