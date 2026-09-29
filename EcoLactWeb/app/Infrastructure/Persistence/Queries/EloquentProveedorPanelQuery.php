<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Proveedor\Contracts\ProveedorPanelQuery;
use App\Application\Proveedor\ProveedorPanelData;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Carbon\CarbonImmutable;

/**
 * Panel lateral de Proveedores (admin). Cada pestaña sale de una sola
 * consulta con sus joins (zona de la entrega, entrega del análisis,
 * producto de la compra): nada de N+1 ni de buscar nombres por separado.
 */
final class EloquentProveedorPanelQuery implements ProveedorPanelQuery
{
    /** Registros más recientes que muestra cada pestaña. */
    public const LIMITE = 15;

    public function obtener(int $proveedorId): ?ProveedorPanelData
    {
        $proveedor = ProveedorModel::with(['zona:id,nombre', 'rutaDetalle.ruta:id,nombre'])->find($proveedorId);

        if ($proveedor === null) {
            return null;
        }

        $asignacion = $proveedor->rutaDetalle;
        $tieneCoordenadas = $asignacion?->latitud !== null && $asignacion?->longitud !== null;

        $compras = VentaProductoModel::query()
            ->where('ventas_producto.proveedor_id', $proveedorId)
            ->where('ventas_producto.forma_pago', 'DESCUENTO_PLANILLA')
            ->where('ventas_producto.estado', '<>', 'ANULADA');

        $resumenCompras = (clone $compras)->toBase()
            ->selectRaw('COUNT(*) as compras, COALESCE(SUM(ventas_producto.importe_total), 0) as total')
            ->first();

        return new ProveedorPanelData(
            id: (int) $proveedor->id,
            nombres: $proveedor->nombres,
            apellidos: $proveedor->apellidos,
            dni: $proveedor->dni,
            telefono: $proveedor->telefono,
            estado: $proveedor->estado,
            fechaRegistro: $proveedor->fecha_registro->format('d/m/Y'),
            qrToken: $proveedor->qr_token,
            zona: $proveedor->zona?->nombre,
            zonaId: $proveedor->zona_id !== null ? (int) $proveedor->zona_id : null,
            ruta: $asignacion?->ruta?->nombre,
            latitud: $tieneCoordenadas ? number_format((float) $asignacion->latitud, 6, '.', '') : null,
            longitud: $tieneCoordenadas ? number_format((float) $asignacion->longitud, 6, '.', '') : null,
            referencia: $asignacion?->referencia_ubicacion ?: null,
            entregas: $this->entregas($proveedorId),
            entregasTotal: EntregaModel::where('proveedor_id', $proveedorId)->count(),
            analisis: $this->analisis($proveedorId),
            analisisTotal: AnalisisCalidadModel::query()
                ->whereHas('entrega', fn ($entrega) => $entrega->where('proveedor_id', $proveedorId))
                ->count(),
            compras: $compras
                ->leftJoin('productos as pr', 'pr.id', '=', 'ventas_producto.producto_id')
                ->orderByDesc('ventas_producto.fecha')
                ->orderByDesc('ventas_producto.id')
                ->limit(self::LIMITE)
                ->toBase()
                ->get(['ventas_producto.id', 'ventas_producto.fecha', 'ventas_producto.cantidad', 'ventas_producto.importe_total', 'ventas_producto.producto_id', 'pr.nombre as producto'])
                ->map(fn (object $fila): array => [
                    'id' => (int) $fila->id,
                    'fecha' => $this->fecha($fila->fecha),
                    'producto' => $fila->producto ?? 'Producto #'.$fila->producto_id,
                    'cantidad' => (float) $fila->cantidad,
                    'total' => round((float) $fila->importe_total, 2),
                ])
                ->all(),
            comprasTotal: (int) $resumenCompras->compras,
            totalDescontado: round((float) $resumenCompras->total, 2),
        );
    }

    /**
     * La zona es la de cada entrega (la zona donde se acopió), no la zona
     * actual del proveedor: si lo reasignaron, el historial no cambia.
     *
     * @return list<array{id: int, fecha: string, zona: string, litros: float, identificacion: string, estado: string}>
     */
    private function entregas(int $proveedorId): array
    {
        return EntregaModel::query()->from('entregas as e')->toBase()
            ->leftJoin('zonas as z', 'z.id', '=', 'e.zona_id')
            ->where('e.proveedor_id', $proveedorId)
            ->orderByDesc('e.fecha_hora')
            ->orderByDesc('e.id')
            ->limit(self::LIMITE)
            ->get(['e.id', 'e.fecha_hora', 'e.litros', 'e.metodo_identificacion', 'e.estado', 'e.zona_id', 'z.nombre as zona_nombre'])
            ->map(fn (object $fila): array => [
                'id' => (int) $fila->id,
                'fecha' => $this->fecha($fila->fecha_hora),
                'zona' => $fila->zona_nombre ?? 'Zona #'.$fila->zona_id,
                'litros' => (float) $fila->litros,
                'identificacion' => $fila->metodo_identificacion,
                'estado' => $fila->estado,
            ])
            ->all();
    }

    /** @return list<array{id: int, fecha: string, entregaId: int, resultado: ?string, estado: string}> */
    private function analisis(int $proveedorId): array
    {
        return AnalisisCalidadModel::query()->from('analisis_calidad as ac')->toBase()
            ->join('entregas as e', 'e.id', '=', 'ac.entrega_id')
            ->where('e.proveedor_id', $proveedorId)
            ->orderByDesc('ac.fecha_analisis')
            ->orderByDesc('ac.id')
            ->limit(self::LIMITE)
            ->get(['ac.id', 'ac.fecha_analisis', 'ac.entrega_id', 'ac.resultado', 'ac.estado'])
            ->map(fn (object $fila): array => [
                'id' => (int) $fila->id,
                'fecha' => $this->fecha($fila->fecha_analisis),
                'entregaId' => (int) $fila->entrega_id,
                'resultado' => $fila->resultado ?: null,
                'estado' => $fila->estado,
            ])
            ->all();
    }

    private function fecha(mixed $valor): string
    {
        return CarbonImmutable::parse($valor, config('app.timezone'))->format('d/m/Y');
    }
}
