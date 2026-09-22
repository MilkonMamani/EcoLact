<?php

namespace App\Application\Produccion;

/**
 * La imagen, el tipo y la presentación pertenecen al PRODUCTO: cada fila de
 * receta los toma de su producto (una sola copia de la imagen).
 *
 * @phpstan-type Ingrediente array{insumoId: int, insumo: string, cantidad: float, unidad: string, observacion: ?string}
 * @phpstan-type Fila array{id: int, productoId: int, producto: string, productoNombreBase: string, tipo: ?string, presentacion: ?string, imagenUrl: ?string, nombre: string, descripcion: ?string, version: int, rendimientoCantidad: float, rendimientoUnidad: string, estado: string, enUso: bool, actualizada: string, ingredientes: list<Ingrediente>}
 */
final readonly class RecetasListadoData
{
    /**
     * @param  array{activas: int, inactivas: int, productosSinReceta: int}  $kpis
     * @param  list<Fila>  $filas
     */
    public function __construct(
        public array $kpis,
        public array $filas,
    ) {}
}
