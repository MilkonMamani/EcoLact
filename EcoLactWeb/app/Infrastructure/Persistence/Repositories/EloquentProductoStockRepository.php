<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Produccion\ProductoMovimiento;
use App\Domain\Produccion\ProductoStock;
use App\Domain\Produccion\ProductoStockRepository;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Calcula el saldo de cada producto sumando sus movimientos con signo. No
 * existe una tabla de saldo: producto_movimientos es la única fuente.
 */
class EloquentProductoStockRepository implements ProductoStockRepository
{
    public function buscarPorProductoId(int $productoId): ?ProductoStock
    {
        $fila = $this->consultaDeSaldos()->where('producto_id', $productoId)->first();

        return $fila ? $this->toEntity($fila) : null;
    }

    /**
     * @return ProductoStock[]
     */
    public function listarTodos(): array
    {
        return $this->consultaDeSaldos()
            ->get()
            ->map(fn (object $fila): ProductoStock => $this->toEntity($fila))
            ->all();
    }

    public function bloquearProducto(int $productoId): void
    {
        ProductoModel::query()->whereKey($productoId)->lockForUpdate()->first();
    }

    private function consultaDeSaldos(): Builder
    {
        $entradas = "'".implode("','", ProductoMovimiento::TIPOS_ENTRADA)."'";

        return DB::table('producto_movimientos')
            ->select('producto_id')
            ->selectRaw("SUM(CASE WHEN tipo IN ({$entradas}) THEN cantidad ELSE -cantidad END) as saldo")
            ->groupBy('producto_id');
    }

    private function toEntity(object $fila): ProductoStock
    {
        return new ProductoStock(
            productoId: (int) $fila->producto_id,
            cantidadActual: max(0.0, round((float) $fila->saldo, 2)),
        );
    }
}
