<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Produccion\Contracts\ProductoDetalleQuery;
use App\Application\Produccion\ProductoDetalleData;
use App\Domain\Produccion\Producto;
use App\Domain\Produccion\ProductoMovimiento;
use App\Infrastructure\Persistence\Models\ProduccionDetalleModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\ProductoMovimientoModel;
use Carbon\CarbonImmutable;

final class EloquentProductoDetalleQuery implements ProductoDetalleQuery
{
    private const ETIQUETAS = [
        'INGRESO_PRODUCCION' => 'Ingreso por producción',
        'VENTA' => 'Venta',
        'AJUSTE_ENTRADA' => 'Ajuste (entrada)',
        'AJUSTE_SALIDA' => 'Ajuste (salida)',
    ];

    public function obtener(int $productoId): ?ProductoDetalleData
    {
        $producto = ProductoModel::with(['recetas' => fn ($q) => $q->orderByDesc('version')])->find($productoId);

        if ($producto === null) {
            return null;
        }

        $movimientos = ProductoMovimientoModel::where('producto_id', $productoId)->orderByDesc('fecha')->orderByDesc('id')->get();
        $stock = max(0.0, round((float) $movimientos->sum(
            fn (ProductoMovimientoModel $m): float => in_array($m->tipo, ProductoMovimiento::TIPOS_ENTRADA, true) ? (float) $m->cantidad : -(float) $m->cantidad,
        ), 2));

        $receta = $producto->recetas->firstWhere('estado', 'ACTIVA') ?? $producto->recetas->first();

        $producciones = ProduccionDetalleModel::with('loteProduccion')
            ->where('producto_id', $productoId)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $ultima = $producciones
            ->filter(fn (ProduccionDetalleModel $d): bool => $d->loteProduccion->estado === 'FINALIZADO' && $d->loteProduccion->fecha_hora_fin !== null)
            ->map(fn (ProduccionDetalleModel $d) => $d->loteProduccion->fecha_hora_fin)
            ->max();

        $presentacion = $producto->presentacion_cantidad !== null && $producto->presentacion_unidad !== null
            ? Producto::formatoCantidad((float) $producto->presentacion_cantidad).' '.$producto->presentacion_unidad
            : null;

        return new ProductoDetalleData(
            id: $producto->id,
            nombre: $producto->nombre,
            nombreBase: $producto->nombre_base ?? $producto->nombre,
            tipo: $producto->tipo_producto,
            presentacion: $presentacion,
            imagenUrl: $producto->imagenUrl(),
            unidadMedida: $producto->unidad_medida,
            estadoProducto: $producto->estado,
            stockActual: $stock,
            estado: $producto->estado !== 'ACTIVO' ? 'INACTIVO' : ($stock > 0 ? 'DISPONIBLE' : 'SIN_STOCK'),
            receta: $receta === null ? null : [
                'id' => $receta->id,
                'nombre' => $receta->nombre,
                'version' => (int) $receta->version,
                'estado' => $receta->estado,
                'descripcion' => $receta->descripcion,
            ],
            ultimaProduccion: $ultima !== null ? CarbonImmutable::parse($ultima, config('app.timezone'))->format('d/m/Y') : null,
            movimientos: $movimientos->take(20)->map(fn (ProductoMovimientoModel $m): array => [
                'fecha' => $m->fecha->format('d/m/Y H:i'),
                'tipo' => self::ETIQUETAS[$m->tipo] ?? $m->tipo,
                'entrada' => in_array($m->tipo, ProductoMovimiento::TIPOS_ENTRADA, true),
                'cantidad' => (float) $m->cantidad,
                'detalle' => $m->observacion ?? ($m->lote_produccion_id ? 'Lote #'.$m->lote_produccion_id : '—'),
            ])->values()->all(),
            producciones: $producciones->map(fn (ProduccionDetalleModel $d): array => [
                'loteId' => $d->lote_produccion_id,
                'codigo' => $d->loteProduccion->codigo,
                'fecha' => $d->loteProduccion->fecha_hora_inicio->format('d/m/Y H:i'),
                'planificado' => (float) $d->cantidad_planificada,
                'obtenido' => $d->cantidad_obtenida !== null ? (float) $d->cantidad_obtenida : null,
                'estado' => $d->loteProduccion->estado,
            ])->values()->all(),
        );
    }
}
