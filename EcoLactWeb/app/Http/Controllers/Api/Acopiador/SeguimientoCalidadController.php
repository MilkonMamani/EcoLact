<?php

namespace App\Http\Controllers\Api\Acopiador;

use App\Application\Acopio\ObtenerEntregas;
use App\Application\Calidad\ObtenerAnalisisCalidad;
use App\Application\Calidad\ObtenerAnalisisPorZona;
use App\Domain\Calidad\AnalisisCalidad;
use App\Domain\Entrega\Entrega;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class SeguimientoCalidadController extends Controller
{
    public function __construct(
        private readonly ObtenerEntregas $obtenerEntregas,
        private readonly ObtenerAnalisisPorZona $obtenerAnalisisPorZona,
        private readonly ObtenerAnalisisCalidad $obtenerAnalisisCalidad,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AnalisisCalidadModel::class);

        $zonaId = $request->user()->acopiador?->zona_id;
        $entregas = $zonaId !== null ? $this->obtenerEntregas->execute($zonaId) : [];

        /** @var Collection<int, AnalisisCalidad> $analisisPorEntrega */
        $analisisPorEntrega = $zonaId !== null
            ? collect($this->obtenerAnalisisPorZona->execute($zonaId))->keyBy(fn (AnalisisCalidad $a) => $a->entregaId())
            : collect();

        $filtradas = collect($entregas)->filter(function (Entrega $entrega) use ($request, $analisisPorEntrega): bool {
            if ($request->filled('proveedor_id') && $entrega->proveedorId() !== $request->integer('proveedor_id')) {
                return false;
            }

            if ($request->filled('estado_analisis') && $entrega->estado()->value !== $request->string('estado_analisis')->toString()) {
                return false;
            }

            if ($request->filled('resultado')) {
                $resultado = $analisisPorEntrega->get($entrega->id())?->resultado()?->value;
                $filtroResultado = $request->string('resultado')->toString();

                if ($filtroResultado === 'sin_resultado' ? $resultado !== null : $resultado !== $filtroResultado) {
                    return false;
                }
            }

            return true;
        })->sortByDesc(fn (Entrega $e) => $e->fechaHora())->values();

        return response()->json([
            'data' => [
                'kpis' => [
                    'pendientes' => $filtradas->filter(fn (Entrega $e): bool => $e->estado()->value === 'PENDIENTE_ANALISIS')->count(),
                    'analizadas' => $filtradas->filter(fn (Entrega $e): bool => $e->estado()->value === 'ANALIZADA')->count(),
                    'aceptadas' => $filtradas->filter(fn (Entrega $e): bool => $analisisPorEntrega->get($e->id())?->resultado()?->value === 'ACEPTADA')->count(),
                    'con_observaciones' => $filtradas->filter(
                        fn (Entrega $e): bool => in_array($analisisPorEntrega->get($e->id())?->resultado()?->value, ['OBSERVADA', 'RECHAZADA'], true)
                    )->count(),
                ],
                'filas' => $filtradas->map(fn (Entrega $e): array => [
                    'id' => $e->id(),
                    'proveedor_id' => $e->proveedorId(),
                    'fecha_hora' => $e->fechaHora()->format(DATE_ATOM),
                    'litros' => $e->litros(),
                    'estado' => $e->estado()->value,
                    'resultado' => $analisisPorEntrega->get($e->id())?->resultado()?->value,
                ])->all(),
            ],
        ]);
    }

    public function show(EntregaModel $entrega): JsonResponse
    {
        Gate::authorize('view', $entrega);

        $analisis = $this->obtenerAnalisisCalidad->porEntrega($entrega->id);

        return response()->json([
            'data' => [
                'entrega_id' => $entrega->id,
                'estado' => $entrega->estado,
                'analisis' => $analisis === null ? null : [
                    'id' => $analisis->id(),
                    'estado' => $analisis->estado()->value,
                    'resultado' => $analisis->resultado()?->value,
                    'fecha_analisis' => $analisis->fechaAnalisis()->format(DATE_ATOM),
                    'observacion' => $analisis->observacion(),
                ],
            ],
        ]);
    }
}
