<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Liquidacion\Contracts\PagosListadoQuery;
use App\Application\Liquidacion\ObtenerPago;
use App\Application\Liquidacion\PagosFiltro;
use App\Domain\Pago\PagoProveedor;
use App\Http\Controllers\Api\Concerns\PaginaFilas;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\PagoProveedorModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class PagoController extends Controller
{
    use PaginaFilas;

    public function __construct(
        private readonly PagosListadoQuery $pagosListadoQuery,
        private readonly ObtenerPago $obtenerPago,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', PagoProveedorModel::class);

        $filtro = PagosFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
            zonaId: $request->filled('zona_id') ? $request->integer('zona_id') : null,
        );

        $datos = $this->pagosListadoQuery->obtener($filtro);
        [$filas, $meta] = $this->paginar($datos->filas, $request);

        return response()->json([
            'data' => ['kpis' => $datos->kpis, 'zonas' => $datos->zonas, 'filas' => $filas],
            'meta' => $meta,
        ]);
    }

    public function show(PagoProveedorModel $pago): JsonResponse
    {
        Gate::authorize('view', $pago);

        return response()->json(['data' => $this->serializar($this->obtenerPago->execute($pago->id))]);
    }

    /** @return array<string, mixed>|null */
    private function serializar(?PagoProveedor $pago): ?array
    {
        if ($pago === null) {
            return null;
        }

        return [
            'id' => $pago->id(),
            'liquidacion_id' => $pago->liquidacionId(),
            'fecha_pago' => $pago->fechaPago()->format(DATE_ATOM),
            'monto' => $pago->monto(),
            'estado' => $pago->estado()->value,
            'registrado_por_user_id' => $pago->registradoPorUserId(),
            'observacion' => $pago->observacion(),
        ];
    }
}
