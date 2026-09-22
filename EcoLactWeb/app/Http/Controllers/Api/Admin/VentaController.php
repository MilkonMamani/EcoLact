<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Ventas\Contracts\VentasListadoQuery;
use App\Application\Ventas\VentasFiltro;
use App\Http\Controllers\Api\Concerns\PaginaFilas;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Solo lectura: ADMINISTRADOR supervisa ventas del negocio, pero registrar
 * una venta es exclusivo de VENTAS (excluido deliberadamente del mockup
 * móvil de Administrador).
 */
final class VentaController extends Controller
{
    use PaginaFilas;

    public function __construct(
        private readonly VentasListadoQuery $ventasListadoQuery,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewGeneral', VentaProductoModel::class);

        $filtro = VentasFiltro::resolver(
            periodo: $request->string('periodo')->toString() ?: null,
            desde: $request->string('desde')->toString() ?: null,
            hasta: $request->string('hasta')->toString() ?: null,
            busqueda: $request->string('q')->toString() ?: null,
            tipoCliente: $request->string('tipo_cliente')->toString() ?: null,
            formaPago: $request->string('forma_pago')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
        );

        $datos = $this->ventasListadoQuery->obtener($filtro);
        [$filas, $meta] = $this->paginar($datos->filas, $request);

        return response()->json([
            'data' => ['kpis' => $datos->kpis, 'filas' => $filas],
            'meta' => $meta,
        ]);
    }

    public function show(VentaProductoModel $venta): JsonResponse
    {
        Gate::authorize('view', $venta);

        $venta->load(['producto', 'proveedor', 'comprador']);

        return response()->json([
            'data' => [
                'id' => $venta->id,
                'producto_id' => $venta->producto_id,
                'producto_nombre' => $venta->producto?->nombre,
                'proveedor_id' => $venta->proveedor_id,
                'comprador_id' => $venta->comprador_id,
                'comprador_nombre' => $venta->comprador->nombre ?? ($venta->proveedor === null ? null : trim($venta->proveedor->nombres.' '.$venta->proveedor->apellidos)),
                'tipo_cliente' => $venta->tipo_cliente,
                'cantidad' => (float) $venta->cantidad,
                'precio_unitario' => (float) $venta->precio_unitario,
                'importe_total' => (float) $venta->importe_total,
                'forma_pago' => $venta->forma_pago,
                'estado' => $venta->estado,
                'fecha' => $venta->fecha?->format(DATE_ATOM),
            ],
        ]);
    }
}
