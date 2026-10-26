<?php

namespace App\Application\Produccion;

/**
 * INVENTARIO -> Productos: productos terminados con sus precios vigentes. El
 * producto lo define su receta y su stock lo mueven la producción y las
 * ventas; aquí solo se consultan y se gestionan sus precios.
 *
 * @phpstan-type Precios array{PROVEEDOR: ?float, PUBLICO: ?float, MAYORISTA: ?float, cantidadMinimaMayorista: ?float}
 * @phpstan-type Fila array{id: int, nombre: string, nombreBase: string, categoria: ?string, presentacion: ?string, imagenUrl: ?string, unidadMedida: string, stockActual: float, recetaEstado: string, precios: Precios, estado: string}
 */
final readonly class ProductosListadoData
{
    /**
     * @param  list<Fila>  $filas
     */
    public function __construct(
        public array $filas,
    ) {}
}
