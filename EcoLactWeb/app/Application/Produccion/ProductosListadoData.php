<?php

namespace App\Application\Produccion;

/**
 * Inventario de productos terminados. No hay acciones de edición: el producto
 * lo define su receta y su stock lo mueven la producción y las ventas.
 *
 * @phpstan-type Fila array{id: int, nombre: string, nombreBase: string, tipo: ?string, presentacion: ?string, imagenUrl: ?string, unidadMedida: string, stockActual: float, recetaEstado: string, ultimaProduccion: ?string, estado: string}
 */
final readonly class ProductosListadoData
{
    /**
     * @param  array{total: int, disponibles: int, sinStock: int, sinReceta: int}  $kpis
     * @param  list<Fila>  $filas
     */
    public function __construct(
        public array $kpis,
        public array $filas,
    ) {}
}
