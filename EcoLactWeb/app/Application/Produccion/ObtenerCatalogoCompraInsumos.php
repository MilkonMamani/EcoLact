<?php

namespace App\Application\Produccion;

use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\InsumoRepository;
use App\Domain\Produccion\PresentacionInsumo;
use App\Domain\Produccion\PresentacionInsumoRepository;

/**
 * Insumos que se pueden COMPRAR (activos y distintos de la leche) con sus
 * presentaciones activas. Alimenta los selectores de la revisión de compra y
 * el reconocimiento de la boleta; dos consultas en total.
 *
 * @phpstan-type PresentacionCatalogo array{id: int, nombre: string, etiqueta: string, cantidad_base: float}
 * @phpstan-type InsumoCatalogo array{id: int, nombre: string, unidad_base: string, stock_actual: float, presentaciones: list<PresentacionCatalogo>}
 */
final class ObtenerCatalogoCompraInsumos
{
    public function __construct(
        private readonly InsumoRepository $insumos,
        private readonly PresentacionInsumoRepository $presentaciones,
    ) {}

    /**
     * @return list<InsumoCatalogo>
     */
    public function execute(): array
    {
        $agrupadas = $this->presentaciones->listarActivasAgrupadas();

        $comprables = array_filter(
            $this->insumos->listarActivos(),
            static fn (Insumo $insumo): bool => ! $insumo->esLeche(),
        );

        return array_values(array_map(fn (Insumo $insumo): array => [
            'id' => (int) $insumo->id(),
            'nombre' => $insumo->nombre(),
            'unidad_base' => $insumo->unidadBase(),
            'stock_actual' => $insumo->stockActual(),
            'presentaciones' => array_map(static fn (PresentacionInsumo $p): array => [
                'id' => (int) $p->id(),
                'nombre' => $p->nombre(),
                'etiqueta' => $p->etiqueta(),
                'cantidad_base' => $p->cantidadBase(),
            ], $agrupadas[(int) $insumo->id()] ?? []),
        ], $comprables));
    }
}
