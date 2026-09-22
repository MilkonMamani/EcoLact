<?php

namespace App\Http\Controllers\Api\Calidad;

use App\Application\Acopio\ObtenerEntregasProveedor;
use App\Application\Calidad\ObtenerAnalisisPorProveedor;
use App\Application\Proveedor\Contracts\ProveedoresListadoQuery;
use App\Application\Proveedor\ObtenerProveedor;
use App\Application\Proveedor\ObtenerZonaHistorialProveedor;
use App\Application\Proveedor\ProveedoresFiltro;
use App\Domain\Calidad\AnalisisCalidad;
use App\Domain\Entrega\Entrega;
use App\Domain\Proveedor\Proveedor;
use App\Http\Controllers\Api\Concerns\PaginaFilas;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Solo lectura, de CUALQUIER zona: sirve para elegir destinatarios de
 * comunicados y revisar el historial de calidad de un proveedor. NO expone
 * liquidaciones ni compras (alcance financiero, ajeno a Calidad).
 */
final class ProveedorController extends Controller
{
    use PaginaFilas;

    public function __construct(
        private readonly ObtenerProveedor $obtenerProveedor,
        private readonly ProveedoresListadoQuery $proveedoresListadoQuery,
        private readonly ObtenerEntregasProveedor $obtenerEntregasProveedor,
        private readonly ObtenerAnalisisPorProveedor $obtenerAnalisisPorProveedor,
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
            'data' => ['kpis' => $datos->kpis, 'zonas' => $datos->zonas, 'filas' => $filas],
            'meta' => $meta,
        ]);
    }

    public function show(ProveedorModel $proveedor): JsonResponse
    {
        Gate::authorize('view', $proveedor);

        return response()->json([
            'data' => [
                'proveedor' => $this->serializarProveedor($this->obtenerProveedor->execute($proveedor->id)),
                'entregas' => array_map($this->serializarEntrega(...), $this->obtenerEntregasProveedor->execute($proveedor->id)),
                'analisis' => array_map(
                    fn (AnalisisCalidad $a): array => [
                        'id' => $a->id(),
                        'entrega_id' => $a->entregaId(),
                        'resultado' => $a->resultado()?->value,
                        'fecha_analisis' => $a->fechaAnalisis()->format(DATE_ATOM),
                    ],
                    $this->obtenerAnalisisPorProveedor->execute($proveedor->id),
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
            'dni' => $proveedor->dni(),
            'nombre_completo' => $proveedor->nombreCompleto(),
            'zona_id' => $proveedor->zonaId(),
            'estado' => $proveedor->estado()->value,
            'advertencias_count' => $proveedor->advertenciasCount(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarEntrega(Entrega $entrega): array
    {
        return [
            'id' => $entrega->id(),
            'fecha_hora' => $entrega->fechaHora()->format(DATE_ATOM),
            'litros' => $entrega->litros(),
            'estado' => $entrega->estado()->value,
        ];
    }
}
