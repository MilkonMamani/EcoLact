<?php

namespace App\Http\Controllers\Web\Planta;

use App\Application\Produccion\CalcularRequerimientosProduccion;
use App\Application\Produccion\Contracts\LotesProduccionListadoQuery;
use App\Application\Produccion\FinalizarProduccion;
use App\Application\Produccion\IniciarPlanificacionProduccion;
use App\Application\Produccion\LotesProduccionFiltro;
use App\Application\Produccion\ObtenerDetalleLoteProduccion;
use App\Application\Produccion\ObtenerProductosProducibles;
use App\Application\Produccion\ObtenerStockLeche;
use App\Http\Controllers\Controller;
use App\Http\Requests\Produccion\FinalizarProduccionRequest;
use App\Http\Requests\Produccion\RequerimientosProduccionRequest;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Portal del ENCARGADO_PLANTA: "Producción" (historial, nueva producción,
 * detalle y finalizar). Reutiliza los MISMOS Use Cases que
 * Web\Produccion\ProduccionController (ningún Use Case ni Repository se
 * duplica); solo cambia la vista servida. El encargado NO configura recetas:
 * solo elige producto + cantidad y el sistema calcula los insumos.
 */
final class ProduccionController extends Controller
{
    public function __construct(
        private readonly LotesProduccionListadoQuery $lotesProduccionListadoQuery,
        private readonly ObtenerDetalleLoteProduccion $obtenerDetalleLoteProduccion,
        private readonly ObtenerStockLeche $obtenerStockLeche,
        private readonly ObtenerProductosProducibles $obtenerProductosProducibles,
        private readonly CalcularRequerimientosProduccion $calcularRequerimientos,
        private readonly IniciarPlanificacionProduccion $iniciarPlanificacion,
        private readonly FinalizarProduccion $finalizarProduccion,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', LoteProduccionModel::class);

        $filtro = LotesProduccionFiltro::resolver(
            busqueda: $request->query('q'),
            estado: $request->query('estado'),
            periodo: $request->query('periodo'),
            desde: $request->query('desde'),
            hasta: $request->query('hasta'),
        );

        return view('planta.produccion.index', [
            'datos' => $this->lotesProduccionListadoQuery->obtener($filtro),
        ] + $this->datosDeNuevaProduccion($request->boolean('nueva')));
    }

    /**
     * "Nueva producción" es un MODAL del historial, no una página. Esta ruta solo existe para
     * enlaces antiguos: redirige al historial abriendo el modal.
     */
    public function create(): RedirectResponse
    {
        Gate::authorize('create', LoteProduccionModel::class);

        return redirect()->route('planta.produccion.index', ['nueva' => 1]);
    }

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

    public function show(LoteProduccionModel $lote): View
    {
        Gate::authorize('view', $lote);

        return view('planta.produccion.show', [
            'detalle' => $this->obtenerDetalleLoteProduccion->execute($lote->id),
            'puedeFinalizar' => Gate::allows('update', $lote),
        ]);
    }

    public function store(RequerimientosProduccionRequest $request): RedirectResponse
    {
        Gate::authorize('create', LoteProduccionModel::class);

        $datos = $request->validated();

        try {
            // Un lote por producto, todos en la misma transacción.
            $iniciada = $this->iniciarPlanificacion->execute(
                lineas: $datos['productos'],
                // El actor es siempre el usuario autenticado, nunca un valor del cliente.
                registradoPorUserId: $request->user()->id,
                observacion: $datos['observacion'] ?? null,
            );
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        // Un solo lote: se abre para seguirlo. Varios: se vuelve al historial, donde aparecen todos.
        $destino = count($iniciada->lotes) === 1
            ? redirect()->route('planta.produccion.show', $iniciada->lotes[0]->id())
            : redirect()->route('planta.produccion.index');

        return $destino->with('success', $iniciada->mensaje());
    }

    public function finalizar(FinalizarProduccionRequest $request, LoteProduccionModel $lote): RedirectResponse
    {
        Gate::authorize('update', $lote);

        $datos = $request->validated();

        try {
            $finalizado = $this->finalizarProduccion->execute(
                loteId: $lote->id,
                cantidadesObtenidas: $datos['obtenido'],
                registradoPorUserId: $request->user()->id,
                observacion: $datos['observacion'] ?? null,
            );
        } catch (DomainException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $detalle = $this->obtenerDetalleLoteProduccion->execute($lote->id);

        return redirect()->route('planta.produccion.show', $lote->id)
            ->with('success', "Producción {$finalizado->codigo()} finalizada. ".$detalle?->resumenStockIncorporado());
    }

    /**
     * Datos del modal "Nueva producción": leche disponible y solo los productos con receta activa.
     *
     * @return array<string, mixed>
     */
    private function datosDeNuevaProduccion(bool $abrir): array
    {
        return [
            'leche' => $this->obtenerStockLeche->execute(),
            'productos' => $this->obtenerProductosProducibles->execute(),
            'rutas' => [
                'requerimientos' => route('planta.produccion.requerimientos'),
                'recetas' => route('planta.recetas.index'),
                'store' => route('planta.produccion.store'),
            ],
            'puedeConfigurarRecetas' => false,
            // El modal se reabre si hubo un error al iniciar o si se llegó desde el enlace interno.
            'abrirModal' => $abrir || session()->has('error') || session()->has('errors'),
        ];
    }
}
