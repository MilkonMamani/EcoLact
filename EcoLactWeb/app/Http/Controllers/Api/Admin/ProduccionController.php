<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Produccion\Contracts\LotesProduccionListadoQuery;
use App\Application\Produccion\LotesProduccionFiltro;
use App\Application\Produccion\ObtenerDetalleLoteProduccion;
use App\Http\Controllers\Api\Concerns\PaginaFilas;
use App\Http\Controllers\Api\Concerns\SerializaLoteProduccion;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Solo lectura: ADMINISTRADOR supervisa producción, pero registrar un lote
 * (o su resultado) es exclusivo de ENCARGADO_PLANTA.
 */
final class ProduccionController extends Controller
{
    use PaginaFilas;
    use SerializaLoteProduccion;

    public function __construct(
        private readonly LotesProduccionListadoQuery $lotesProduccionListadoQuery,
        private readonly ObtenerDetalleLoteProduccion $obtenerDetalleLoteProduccion,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', LoteProduccionModel::class);

        $filtro = LotesProduccionFiltro::resolver(
            busqueda: $request->query('q'),
            estado: $request->query('estado'),
            periodo: $request->query('periodo'),
            desde: $request->query('desde'),
            hasta: $request->query('hasta'),
        );

        $datos = $this->lotesProduccionListadoQuery->obtener($filtro);
        [$filas, $meta] = $this->paginar($datos->filas, $request);

        return response()->json([
            'data' => ['kpis' => $datos->kpis, 'filas' => $filas],
            'meta' => $meta,
        ]);
    }

    public function show(LoteProduccionModel $lote): JsonResponse
    {
        Gate::authorize('view', $lote);

        $detalle = $this->obtenerDetalleLoteProduccion->execute($lote->id);

        if ($detalle === null) {
            abort(404);
        }

        return response()->json(['data' => $this->serializarLote($detalle)]);
    }
}
