<?php

namespace App\Http\Controllers\Api\Planta;

use App\Application\Produccion\CalcularRequerimientosProduccion;
use App\Application\Produccion\Contracts\LotesProduccionListadoQuery;
use App\Application\Produccion\FinalizarProduccion;
use App\Application\Produccion\IniciarProduccion;
use App\Application\Produccion\LotesProduccionFiltro;
use App\Application\Produccion\ObtenerDetalleLoteProduccion;
use App\Application\Produccion\ObtenerStockLeche;
use App\Application\Produccion\ProduccionBloqueadaException;
use App\Http\Controllers\Api\Concerns\PaginaFilas;
use App\Http\Controllers\Api\Concerns\SerializaLoteProduccion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Produccion\FinalizarProduccionRequest;
use App\Http\Requests\Produccion\RequerimientosProduccionRequest;
use App\Http\Support\FechaEventoMovil;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ProduccionController extends Controller
{
    use PaginaFilas;
    use SerializaLoteProduccion;

    public function __construct(
        private readonly LotesProduccionListadoQuery $lotesProduccionListadoQuery,
        private readonly ObtenerDetalleLoteProduccion $obtenerDetalleLoteProduccion,
        private readonly ObtenerStockLeche $obtenerStockLeche,
        private readonly CalcularRequerimientosProduccion $calcularRequerimientos,
        private readonly IniciarProduccion $iniciarProduccion,
        private readonly FinalizarProduccion $finalizarProduccion,
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

    /**
     * Stock de leche del inventario (insumo LECHE). Sube automáticamente con
     * cada entrega de proveedores y baja al iniciar una producción.
     */
    public function lecheDisponible(): JsonResponse
    {
        Gate::authorize('create', LoteProduccionModel::class);

        $leche = $this->obtenerStockLeche->execute();

        return response()->json(['data' => ['litros_disponibles' => $leche->stockActual(), 'unidad' => $leche->unidadBase()]]);
    }

    /**
     * Previsualiza los insumos que necesitaría una producción, sin descontar
     * nada. Es el mismo cálculo que usa "iniciar".
     */
    public function requerimientos(RequerimientosProduccionRequest $request): JsonResponse
    {
        Gate::authorize('create', LoteProduccionModel::class);

        try {
            $requerimientos = $this->calcularRequerimientos->execute($request->validated()['productos']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $requerimientos->toArray()]);
    }

    public function show(LoteProduccionModel $lote): JsonResponse
    {
        Gate::authorize('view', $lote);

        $detalle = $this->obtenerDetalleLoteProduccion->execute($lote->id);

        if ($detalle === null) {
            abort(404);
        }

        return response()->json(['data' => ['lote' => $this->serializarLote($detalle)]]);
    }

    /**
     * Inicia una producción: elige productos y cantidades; los insumos se
     * calculan con la receta y se descuentan en una sola transacción.
     */
    public function store(RequerimientosProduccionRequest $request): JsonResponse
    {
        Gate::authorize('create', LoteProduccionModel::class);

        $datos = $request->validated();

        try {
            $lote = $this->iniciarProduccion->execute(
                lineas: $datos['productos'],
                registradoPorUserId: $request->user()->id,
                observacion: $datos['observacion'] ?? null,
                // Hora real del inicio en el teléfono (offline), no la de sincronización.
                fechaHoraInicio: FechaEventoMovil::parse($datos['fecha_hora_evento'] ?? null),
            );
        } catch (ProduccionBloqueadaException $e) {
            return response()->json(['message' => $e->getMessage(), 'motivos' => $e->motivos], 422);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['id' => $lote->id(), 'codigo' => $lote->codigo(), 'estado' => $lote->estado()]], 201);
    }

    /**
     * Finaliza el lote con las cantidades realmente obtenidas y las ingresa al
     * inventario de productos. No vuelve a descontar insumos.
     */
    public function finalizar(FinalizarProduccionRequest $request, LoteProduccionModel $lote): JsonResponse
    {
        Gate::authorize('update', $lote);

        $datos = $request->validated();

        try {
            $this->finalizarProduccion->execute(
                loteId: $lote->id,
                cantidadesObtenidas: $datos['obtenido'],
                registradoPorUserId: $request->user()->id,
                fechaHoraFin: FechaEventoMovil::parse($datos['fecha_hora_evento'] ?? null),
                observacion: $datos['observacion'] ?? null,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $detalle = $this->obtenerDetalleLoteProduccion->execute($lote->id);

        return response()->json(['data' => $detalle === null ? null : $this->serializarLote($detalle)]);
    }
}
