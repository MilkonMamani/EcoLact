<?php

namespace App\Application\Produccion;

/**
 * Ficha de CONSULTA de un producto terminado (no se edita desde aquí: la
 * fórmula y la imagen se gestionan desde su receta).
 *
 * @phpstan-type Receta array{id: int, nombre: string, version: int, estado: string, descripcion: ?string}
 * @phpstan-type Movimiento array{fecha: string, tipo: string, entrada: bool, cantidad: float, detalle: string}
 * @phpstan-type Produccion array{loteId: int, codigo: string, fecha: string, planificado: float, obtenido: ?float, estado: string}
 */
final readonly class ProductoDetalleData
{
    /**
     * @param  Receta|null  $receta  la receta activa, o la más reciente si ninguna está activa
     * @param  list<Movimiento>  $movimientos
     * @param  list<Produccion>  $producciones
     */
    public function __construct(
        public int $id,
        public string $nombre,
        public string $nombreBase,
        public ?string $tipo,
        public ?string $presentacion,
        public ?string $imagenUrl,
        public string $unidadMedida,
        public string $estadoProducto,
        public float $stockActual,
        public string $estado,
        public ?array $receta,
        public ?string $ultimaProduccion,
        public array $movimientos,
        public array $producciones,
    ) {}
}
