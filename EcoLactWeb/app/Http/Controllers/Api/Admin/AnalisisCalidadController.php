<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Calidad\AnalisisCalidadFiltro;
use App\Application\Calidad\Contracts\AnalisisCalidadListadoQuery;
use App\Application\Calidad\ObtenerAnalisisCalidad;
use App\Domain\Calidad\AnalisisCalidad;
use App\Http\Controllers\Api\Concerns\PaginaFilas;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Solo lectura: ADMINISTRADOR supervisa calidad (KPIs, listado, detalle),
 * pero no analiza ni confirma (eso es exclusivo de CALIDAD).
 */
final class AnalisisCalidadController extends Controller
{
    use PaginaFilas;

    public function __construct(
        private readonly AnalisisCalidadListadoQuery $analisisCalidadListadoQuery,
        private readonly ObtenerAnalisisCalidad $obtenerAnalisisCalidad,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AnalisisCalidadModel::class);

        $filtro = AnalisisCalidadFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            zonaId: $request->filled('zona_id') ? $request->integer('zona_id') : null,
            resultado: $request->string('resultado')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
            fecha: $request->string('fecha')->toString() ?: null,
            desde: $request->string('desde')->toString() ?: null,
            hasta: $request->string('hasta')->toString() ?: null,
        );

        $datos = $this->analisisCalidadListadoQuery->obtener($filtro);
        [$filas, $meta] = $this->paginar($datos->filas, $request);

        return response()->json([
            'data' => ['kpis' => $datos->kpis, 'zonas' => $datos->zonas, 'filas' => $filas],
            'meta' => $meta,
        ]);
    }

    public function show(AnalisisCalidadModel $analisis): JsonResponse
    {
        Gate::authorize('view', $analisis);

        return response()->json(['data' => $this->serializar($this->obtenerAnalisisCalidad->porId($analisis->id))]);
    }

    /** @return array<string, mixed>|null */
    private function serializar(?AnalisisCalidad $analisis): ?array
    {
        if ($analisis === null) {
            return null;
        }

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
}
