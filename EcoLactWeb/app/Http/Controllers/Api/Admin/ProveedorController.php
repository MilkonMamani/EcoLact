<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Acopio\ObtenerEntregasProveedor;
use App\Application\Calidad\ObtenerAnalisisPorProveedor;
use App\Application\Liquidacion\ObtenerLiquidaciones;
use App\Application\Proveedor\Contracts\ProveedoresListadoQuery;
use App\Application\Proveedor\ObtenerProveedor;
use App\Application\Proveedor\ObtenerZonaHistorialProveedor;
use App\Application\Proveedor\ProveedoresFiltro;
use App\Application\Ventas\ObtenerVentasProveedor;
use App\Domain\Calidad\AnalisisCalidad;
use App\Domain\Entrega\Entrega;
use App\Domain\Pago\Liquidacion;
use App\Domain\Proveedor\Proveedor;
use App\Domain\Proveedor\ProveedorZonaHistorial;
use App\Domain\Ventas\VentaProducto;
use App\Http\Controllers\Api\Concerns\PaginaFilas;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ProveedorController extends Controller
{
    use PaginaFilas;

    public function __construct(
        private readonly ObtenerProveedor $obtenerProveedor,
        private readonly ProveedoresListadoQuery $proveedoresListadoQuery,
        private readonly ObtenerEntregasProveedor $obtenerEntregasProveedor,
        private readonly ObtenerAnalisisPorProveedor $obtenerAnalisisPorProveedor,
        private readonly ObtenerLiquidaciones $obtenerLiquidaciones,
        private readonly ObtenerVentasProveedor $obtenerVentasProveedor,
        private readonly ObtenerZonaHistorialProveedor $obtenerZonaHistorialProveedor,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ProveedorModel::class);

        $filtro = ProveedoresFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
            zonaId: $request->filled('zona_id') ? $request->integer('zona_id') : null,
            seguimiento: $request->string('seguimiento')->toString() ?: null,
        );

        $datos = $this->proveedoresListadoQuery->obtener($filtro);
        [$filas, $meta] = $this->paginar($datos->filas, $request);

        return response()->json([
            'data' => [
                'kpis' => $datos->kpis,
                'zonas' => $datos->zonas,
                'filas' => $filas,
            ],
            'meta' => $meta,
        ]);
    }

    public function show(ProveedorModel $proveedor): JsonResponse
    {
        Gate::authorize('view', $proveedor);

        return response()->json([
            'data' => [
                'proveedor' => $this->serializarProveedor($this->obtenerProveedor->execute($proveedor->id)),
                'entregas' => array_map(
                    $this->serializarEntrega(...),
                    $this->obtenerEntregasProveedor->execute($proveedor->id),
                ),
                'analisis' => array_map(
                    $this->serializarAnalisis(...),
                    $this->obtenerAnalisisPorProveedor->execute($proveedor->id),
                ),
                'liquidaciones' => array_map(
                    $this->serializarLiquidacion(...),
                    $this->obtenerLiquidaciones->execute($proveedor->id),
                ),
                'compras' => array_map(
                    $this->serializarCompra(...),
                    $this->obtenerVentasProveedor->execute($proveedor->id),
                ),
                'zona_historial' => array_map(
                    $this->serializarZonaHistorial(...),
                    $this->obtenerZonaHistorialProveedor->execute($proveedor->id),
                ),
            ],
        ]);
    }

    /** @return array<string, mixed>|null */
    private function serializarProveedor(?Proveedor $proveedor): ?array
    {
        if ($proveedor === null) {
            return null;
        }

        return [
            'id' => $proveedor->id(),
            'user_id' => $proveedor->userId(),
            'zona_id' => $proveedor->zonaId(),
            'dni' => $proveedor->dni(),
            'nombres' => $proveedor->nombres(),
            'apellidos' => $proveedor->apellidos(),
            'nombre_completo' => $proveedor->nombreCompleto(),
            'telefono' => $proveedor->telefono(),
            'estado' => $proveedor->estado()->value,
            'fecha_registro' => $proveedor->fechaRegistro()->format(DATE_ATOM),
            'advertencias_count' => $proveedor->advertenciasCount(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarEntrega(Entrega $entrega): array
    {
        return [
            'id' => $entrega->id(),
            'proveedor_id' => $entrega->proveedorId(),
            'acopiador_id' => $entrega->acopiadorId(),
            'zona_id' => $entrega->zonaId(),
            'registrado_por_user_id' => $entrega->registradoPorUserId(),
            'fecha_hora' => $entrega->fechaHora()->format(DATE_ATOM),
            'litros' => $entrega->litros(),
            'metodo_identificacion' => $entrega->metodoIdentificacion(),
            'estado' => $entrega->estado()->value,
            'observacion' => $entrega->observacion(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarAnalisis(AnalisisCalidad $analisis): array
    {
        return [
            'id' => $analisis->id(),
            'entrega_id' => $analisis->entregaId(),
            'imagen_path' => $analisis->imagenPath(),
            'estado' => $analisis->estado()->value,
            'resultado' => $analisis->resultado()?->value,
            'fecha_analisis' => $analisis->fechaAnalisis()->format(DATE_ATOM),
            'confirmado_por_user_id' => $analisis->confirmadoPorUserId(),
            'observacion' => $analisis->observacion(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarLiquidacion(Liquidacion $liquidacion): array
    {
        return [
            'id' => $liquidacion->id(),
            'periodo_id' => $liquidacion->periodoId(),
            'proveedor_id' => $liquidacion->proveedorId(),
            'total_litros_entregados' => $liquidacion->totalLitrosEntregados(),
            'total_litros_pagables' => $liquidacion->totalLitrosPagables(),
            'subtotal' => $liquidacion->subtotal(),
            'total_ajustes' => $liquidacion->totalAjustes(),
            'total_pagar' => $liquidacion->totalPagar(),
            'estado' => $liquidacion->estado()->value,
            'fecha_calculo' => $liquidacion->fechaCalculo()->format(DATE_ATOM),
            'precio_litro_aplicado' => $liquidacion->precioLitroAplicado(),
            'tuvo_incidencia' => $liquidacion->tuvoIncidencia(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarCompra(VentaProducto $venta): array
    {
        return [
            'id' => $venta->id(),
            'producto_id' => $venta->productoId(),
            'proveedor_id' => $venta->proveedorId(),
            'comprador_id' => $venta->compradorId(),
            'tipo_cliente' => $venta->tipoCliente(),
            'cantidad' => $venta->cantidad(),
            'precio_unitario' => $venta->precioUnitario(),
            'importe_total' => $venta->importeTotal(),
            'forma_pago' => $venta->formaPago(),
            'periodo_id' => $venta->periodoId(),
            'estado' => $venta->estado(),
            'fecha' => $venta->fecha()->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarZonaHistorial(ProveedorZonaHistorial $historial): array
    {
        return [
            'id' => $historial->id(),
            'proveedor_id' => $historial->proveedorId(),
            'zona_id' => $historial->zonaId(),
            'fecha_inicio' => $historial->fechaInicio()->format(DATE_ATOM),
            'fecha_fin' => $historial->fechaFin()?->format(DATE_ATOM),
            'motivo' => $historial->motivo(),
            'esta_vigente' => $historial->estaVigente(),
        ];
    }
}
