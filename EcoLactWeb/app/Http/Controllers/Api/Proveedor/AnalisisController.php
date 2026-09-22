<?php

namespace App\Http\Controllers\Api\Proveedor;

use App\Application\Calidad\ObtenerAnalisisCalidad;
use App\Application\Calidad\ObtenerAnalisisProveedor;
use App\Domain\Calidad\AnalisisCalidad;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * "Mi calidad": autoservicio del PROVEEDOR sobre sus propios análisis.
 */
final class AnalisisController extends Controller
{
    public function __construct(
        private readonly ObtenerAnalisisProveedor $obtenerAnalisisProveedor,
        private readonly ObtenerAnalisisCalidad $obtenerAnalisisCalidad,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $analisis = $this->obtenerAnalisisProveedor->execute($request->user()->id);

        $filtrados = collect($analisis)->filter(function (AnalisisCalidad $item) use ($request): bool {
            if ($request->filled('resultado') && $item->resultado()?->value !== $request->string('resultado')->toString()) {
                return false;
            }

            if ($request->filled('estado') && $item->estado()->value !== $request->string('estado')->toString()) {
                return false;
            }

            return true;
        })->sortByDesc(fn (AnalisisCalidad $item) => $item->fechaAnalisis())->values();

        return response()->json([
            'data' => [
                'kpis' => [
                    'total' => $filtrados->count(),
                    'aceptados' => $filtrados->filter(fn (AnalisisCalidad $a): bool => $a->resultado()?->value === 'ACEPTADA')->count(),
                    'observados' => $filtrados->filter(fn (AnalisisCalidad $a): bool => $a->resultado()?->value === 'OBSERVADA')->count(),
                    'rechazados' => $filtrados->filter(fn (AnalisisCalidad $a): bool => $a->resultado()?->value === 'RECHAZADA')->count(),
                ],
                'filas' => $filtrados->map($this->serializar(...))->all(),
            ],
        ]);
    }

    public function show(AnalisisCalidadModel $analisis): JsonResponse
    {
        Gate::authorize('view', $analisis);

        $analisis->load(['entrega.acopiador', 'entrega.zona', 'detalles.parametro']);
        $sancion = $analisis->sanciones()->latest('fecha')->first();

        return response()->json([
            'data' => [
                'id' => $analisis->id,
                'entrega_id' => $analisis->entrega_id,
                'resultado' => $analisis->resultado,
                'estado' => $analisis->estado,
                'fecha_analisis' => $analisis->fecha_analisis?->format(DATE_ATOM),
                'observacion' => $analisis->observacion,
                'entrega' => $analisis->entrega === null ? null : [
                    'litros' => (float) $analisis->entrega->litros,
                    'zona_nombre' => $analisis->entrega->zona?->nombre,
                ],
                'detalles' => $analisis->detalles->map(fn ($detalle): array => [
                    'parametro_codigo' => $detalle->parametro?->codigo,
                    'parametro_nombre' => $detalle->parametro?->nombre,
                    'unidad' => $detalle->parametro?->unidad,
                    'valor' => (float) $detalle->valor,
                    'cumple' => $detalle->cumple,
                ])->all(),
                'sancion' => $sancion === null ? null : [
                    'id' => $sancion->id,
                    'tipo' => $sancion->tipo,
                    'descripcion' => $sancion->descripcion,
                ],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function serializar(AnalisisCalidad $analisis): array
    {
        return [
            'id' => $analisis->id(),
            'entrega_id' => $analisis->entregaId(),
            'resultado' => $analisis->resultado()?->value,
            'estado' => $analisis->estado()->value,
            'fecha_analisis' => $analisis->fechaAnalisis()->format(DATE_ATOM),
        ];
    }
}
