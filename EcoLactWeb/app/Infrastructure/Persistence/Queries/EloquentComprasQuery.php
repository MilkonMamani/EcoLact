<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Compra\CompraFila;
use App\Application\Compra\ComprasFiltro;
use App\Application\Compra\ComprasResumen;
use App\Application\Compra\Contracts\ComprasQuery;
use App\Domain\Produccion\CompraInsumo;
use App\Domain\Shared\Enums\EstadoPagoCompra;
use App\Domain\Shared\Enums\EstadoRecepcionCompra;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Historial e indicadores de Compras y gastos desde compras_insumo, con
 * paginación y agregados en SQL. Lo pagado sale de la columna monto_pagado,
 * que RegistrarPagoCompra mantiene igual a la suma de pagos_compra.
 */
class EloquentComprasQuery implements ComprasQuery
{
    public function resumen(ComprasFiltro $filtro): ComprasResumen
    {
        $fila = $this->filtrada($filtro)
            ->selectRaw('COUNT(*) as compras, COALESCE(SUM(c.total), 0) as comprado, COALESCE(SUM(c.monto_pagado), 0) as pagado')
            ->selectRaw('COALESCE(SUM(CASE WHEN c.total > c.monto_pagado THEN c.total - c.monto_pagado ELSE 0 END), 0) as pendiente')
            ->first();

        return new ComprasResumen(
            compras: (int) $fila->compras,
            comprado: round((float) $fila->comprado, 2),
            pagado: round((float) $fila->pagado, 2),
            pendiente: round((float) $fila->pendiente, 2),
        );
    }

    public function listar(ComprasFiltro $filtro): LengthAwarePaginator
    {
        $pagina = $this->filtrada($filtro)
            ->leftJoin('categorias_compra as cat', 'cat.id', '=', 'c.categoria_compra_id')
            ->select('c.id', 'c.codigo', 'c.fecha', 'c.concepto', 'c.proveedor_comercial', 'c.tipo_comprobante', 'c.numero_comprobante', 'c.total', 'c.monto_pagado', 'c.estado_pago', 'c.estado_recepcion', 'cat.nombre as categoria')
            ->selectSub($this->lineas('INVENTARIO'), 'lineas_inventario')
            ->selectSub($this->lineas(null), 'lineas_total')
            ->orderByDesc('c.fecha')
            ->orderByDesc('c.id')
            ->paginate($filtro->porPagina)
            ->withQueryString();

        $articulos = $this->primerosArticulos(array_map(fn (object $fila): int => (int) $fila->id, $pagina->items()));

        return $pagina->through(fn (object $fila): CompraFila => $this->fila($fila, $articulos[(int) $fila->id] ?? null));
    }

    /**
     * Primera línea de cada compra de la página, resumida: "Cuajo · 8 baldes"
     * (inventario) o "Gaseosa 3 L · 4 und" (gasto). Una sola consulta.
     *
     * @param  list<int>  $compraIds
     * @return array<int, string>
     */
    private function primerosArticulos(array $compraIds): array
    {
        if ($compraIds === []) {
            return [];
        }

        $primeras = DB::table('compra_insumo_detalles as d')
            ->leftJoin('insumos as i', 'i.id', '=', 'd.insumo_id')
            ->whereIn('d.id', DB::table('compra_insumo_detalles')->selectRaw('MIN(id)')->whereIn('compra_insumo_id', $compraIds)->groupBy('compra_insumo_id'))
            ->get(['d.compra_insumo_id', 'd.tratamiento', 'd.descripcion', 'd.unidad', 'd.presentacion_nombre', 'd.cantidad_presentaciones', 'i.nombre as insumo']);

        $articulos = [];
        foreach ($primeras as $linea) {
            $articulos[(int) $linea->compra_insumo_id] = $this->resumirLinea($linea);
        }

        return $articulos;
    }

    private function resumirLinea(object $linea): string
    {
        $cantidad = (float) $linea->cantidad_presentaciones;
        $numero = rtrim(rtrim(number_format($cantidad, 2, '.', ','), '0'), '.');

        if ($linea->tratamiento === 'INVENTARIO' && $linea->insumo !== null) {
            $presentacion = (string) $linea->presentacion_nombre;
            // "8 baldes", "1 balde"; un nombre compuesto ("Bolsa 25 kg") se deja como está.
            $empaque = $cantidad != 1.0 && $presentacion !== '' && ! str_contains($presentacion, ' ')
                ? mb_strtolower($presentacion).(preg_match('/[aeiouáéíóú]$/iu', $presentacion) === 1 ? 's' : 'es')
                : mb_strtolower($presentacion);

            return trim($linea->insumo.' · '.$numero.' '.$empaque);
        }

        return trim(($linea->descripcion ?? 'Gasto').' · '.$numero.' '.($linea->unidad ?? 'und'));
    }

    private function filtrada(ComprasFiltro $filtro): Builder
    {
        $query = DB::table('compras_insumo as c');

        if ($filtro->desde !== null) {
            $query->where('c.fecha', '>=', $filtro->desde->format('Y-m-d'));
        }
        if ($filtro->hasta !== null) {
            $query->where('c.fecha', '<=', $filtro->hasta->format('Y-m-d'));
        }
        if ($filtro->categoriaId !== null) {
            $query->where('c.categoria_compra_id', $filtro->categoriaId);
        }
        if ($filtro->estadoPago !== null) {
            $query->where('c.estado_pago', $filtro->estadoPago->value);
        }
        if ($filtro->destino !== null) {
            $conInventario = fn (Builder $sub): Builder => $sub->selectRaw('1')->from('compra_insumo_detalles as d')
                ->whereColumn('d.compra_insumo_id', 'c.id')
                ->where('d.tratamiento', 'INVENTARIO');
            $filtro->destino === ComprasFiltro::DESTINO_INVENTARIO ? $query->whereExists($conInventario) : $query->whereNotExists($conInventario);
        }
        if ($filtro->busqueda !== null) {
            $patron = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filtro->busqueda).'%';
            $query->where(function (Builder $query) use ($patron): void {
                $query->where('c.concepto', 'like', $patron)
                    ->orWhere('c.codigo', 'like', $patron)
                    ->orWhere('c.numero_comprobante', 'like', $patron)
                    ->orWhere('c.proveedor_comercial', 'like', $patron)
                    ->orWhere('c.ruc', 'like', $patron)
                    // También por lo que se compró: "cuajo", "gaseosa"...
                    ->orWhereExists(fn (Builder $sub): Builder => $sub->selectRaw('1')->from('compra_insumo_detalles as dl')
                        ->leftJoin('insumos as il', 'il.id', '=', 'dl.insumo_id')
                        ->whereColumn('dl.compra_insumo_id', 'c.id')
                        ->where(fn (Builder $q): Builder => $q->where('dl.descripcion', 'like', $patron)->orWhere('il.nombre', 'like', $patron)));
            });
        }

        return $query;
    }

    private function lineas(?string $tratamiento): Builder
    {
        $sub = DB::table('compra_insumo_detalles as d')->selectRaw('COUNT(*)')->whereColumn('d.compra_insumo_id', 'c.id');

        return $tratamiento !== null ? $sub->where('d.tratamiento', $tratamiento) : $sub;
    }

    private function fila(object $fila, ?string $articulo): CompraFila
    {
        $inventario = (int) $fila->lineas_inventario;
        $total = $fila->total !== null ? round((float) $fila->total, 2) : null;
        $pagado = round((float) $fila->monto_pagado, 2);

        return new CompraFila(
            id: (int) $fila->id,
            codigo: $fila->codigo ?? CompraInsumo::codigoPara((int) $fila->id),
            fecha: CarbonImmutable::parse($fila->fecha),
            concepto: $fila->concepto ?? 'Compra',
            proveedor: $fila->proveedor_comercial,
            comprobante: $fila->numero_comprobante,
            categoria: $fila->categoria,
            destino: match (true) {
                $inventario === 0 => 'GASTO',
                $inventario === (int) $fila->lineas_total => 'INVENTARIO',
                default => 'MIXTO',
            },
            total: $total,
            pagado: $pagado,
            estadoPago: EstadoPagoCompra::tryFrom((string) $fila->estado_pago) ?? EstadoPagoCompra::desde($total, $pagado),
            estadoRecepcion: EstadoRecepcionCompra::tryFrom((string) $fila->estado_recepcion) ?? EstadoRecepcionCompra::RECIBIDA,
            articulo: $articulo,
            articulosAdicionales: max(0, (int) $fila->lineas_total - 1),
        );
    }
}
