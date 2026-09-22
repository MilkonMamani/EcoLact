<?php

namespace App\Http\Controllers\Api\Calidad;

use App\Application\Acopio\Contracts\EntregasListadoQuery;
use App\Application\Acopio\EntregasFiltro;
use App\Application\Calidad\AnalisisCalidadFiltro;
use App\Application\Calidad\Contracts\AnalisisCalidadListadoQuery;
use App\Application\Calidad\FinalizarAnalisisCalidad;
use App\Application\Calidad\ObtenerAnalisisCalidad;
use App\Application\Calidad\ObtenerParametrosCalidad;
use App\Application\Calidad\RegistrarAnalisisCalidad;
use App\Domain\Calidad\AnalisisCalidad;
use App\Http\Controllers\Api\Concerns\PaginaFilas;
use App\Http\Controllers\Controller;
use App\Http\Requests\Calidad\FinalizarAnalisisCalidadRequest;
use App\Http\Requests\Calidad\StoreAnalisisCalidadRequest;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class AnalisisCalidadController extends Controller
{
    use PaginaFilas;

    public function __construct(
        private readonly RegistrarAnalisisCalidad $registrarAnalisisCalidad,
        private readonly FinalizarAnalisisCalidad $finalizarAnalisisCalidad,
        private readonly ObtenerAnalisisCalidad $obtenerAnalisisCalidad,
        private readonly EntregasListadoQuery $entregasListadoQuery,
        private readonly AnalisisCalidadListadoQuery $analisisCalidadListadoQuery,
        private readonly ObtenerParametrosCalidad $obtenerParametrosCalidad,
    ) {}

    /**
     * "Pendientes" (entregas sin análisis) o "Historial" (análisis ya
     * registrados), según ?tab=. Mismo criterio que calidad.analisis-calidad.index.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AnalisisCalidadModel::class);

        $tab = $request->query('tab') === 'historial' ? 'historial' : 'pendientes';

        if ($tab === 'pendientes') {
            $pendientes = $this->entregasListadoQuery->obtener(EntregasFiltro::resolver(
                busqueda: $request->string('q')->toString() ?: null,
                zonaId: $request->filled('zona_id') ? (int) $request->query('zona_id') : null,
                acopiadorId: null,
                estado: 'PENDIENTE_ANALISIS',
                fecha: $request->string('fecha')->toString() ?: null,
                desde: $request->string('desde')->toString() ?: null,
                hasta: $request->string('hasta')->toString() ?: null,
            ));

            [$filas, $meta] = $this->paginar($pendientes->filas, $request);

            return response()->json([
                'data' => ['tab' => 'pendientes', 'kpis' => $pendientes->kpis, 'zonas' => $pendientes->zonas, 'filas' => $filas],
                'meta' => $meta,
            ]);
        }

        $historial = $this->analisisCalidadListadoQuery->obtener(AnalisisCalidadFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            zonaId: $request->filled('zona_id') ? (int) $request->query('zona_id') : null,
            resultado: $request->string('resultado')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
            fecha: $request->string('fecha')->toString() ?: null,
            desde: $request->string('desde')->toString() ?: null,
            hasta: $request->string('hasta')->toString() ?: null,
        ));

        [$filas, $meta] = $this->paginar($historial->filas, $request);

        return response()->json([
            'data' => ['tab' => 'historial', 'kpis' => $historial->kpis, 'zonas' => $historial->zonas, 'filas' => $filas],
            'meta' => $meta,
        ]);
    }

    public function show(AnalisisCalidadModel $analisis): JsonResponse
    {
        Gate::authorize('view', $analisis);

        $analisis->load(['entrega.proveedor', 'entrega.acopiador', 'entrega.zona', 'confirmadoPor', 'detalles.parametro']);

        return response()->json(['data' => $this->serializarConDetalles($analisis)]);
    }

    public function porEntrega(EntregaModel $entrega): JsonResponse
    {
        Gate::authorize('view', $entrega);

        $analisis = $this->obtenerAnalisisCalidad->porEntrega($entrega->id);

        return response()->json([
            'data' => [
                'analisis' => $this->serializar($analisis),
                'parametros' => array_values(array_map(
                    static fn ($parametro): array => [
                        'id' => $parametro->id(),
                        'codigo' => $parametro->codigo(),
                        'nombre' => $parametro->nombre(),
                        'unidad' => $parametro->unidad(),
                    ],
                    array_filter($this->obtenerParametrosCalidad->execute(), static fn ($parametro): bool => $parametro->estaActivo()),
                )),
            ],
        ]);
    }

    /**
     * Registra (o continúa, mientras esté BORRADOR) el análisis de una
     * entrega. `valores` va indexado por CÓDIGO de parámetro, ej.
     * {"DENSIDAD": 1.029, "PH": 6.72}.
     */
    public function store(StoreAnalisisCalidadRequest $request): JsonResponse
    {
        Gate::authorize('create', AnalisisCalidadModel::class);

        $datos = $request->validated();

        $imagenPath = $request->hasFile('imagen')
            ? $request->file('imagen')->store('analisis-calidad', 'public')
            : null;

        $analisis = $this->registrarAnalisisCalidad->execute(
            entregaId: (int) $datos['entrega_id'],
            valores: $datos['valores'],
            imagenPath: $imagenPath,
            observacion: $datos['observacion'] ?? null,
        );

        return response()->json(['data' => $this->serializar($analisis)], 201);
    }

    public function finalizar(FinalizarAnalisisCalidadRequest $request, AnalisisCalidadModel $analisis): JsonResponse
    {
        Gate::authorize('update', $analisis);

        $this->finalizarAnalisisCalidad->execute($analisis->id, $request->user()->id);

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

    /** @return array<string, mixed> */
    private function serializarConDetalles(AnalisisCalidadModel $analisis): array
    {
        return [
            'id' => $analisis->id,
            'entrega_id' => $analisis->entrega_id,
            'imagen_path' => $analisis->imagen_path,
            'estado' => $analisis->estado,
            'resultado' => $analisis->resultado,
            'fecha_analisis' => $analisis->fecha_analisis?->format(DATE_ATOM),
            'observacion' => $analisis->observacion,
            'entrega' => $analisis->entrega === null ? null : [
                'id' => $analisis->entrega->id,
                'litros' => (float) $analisis->entrega->litros,
                'fecha_hora' => $analisis->entrega->fecha_hora?->format(DATE_ATOM),
                'proveedor_nombre' => trim(($analisis->entrega->proveedor?->nombres ?? '').' '.($analisis->entrega->proveedor?->apellidos ?? '')),
                'zona_nombre' => $analisis->entrega->zona?->nombre,
            ],
            'detalles' => $analisis->detalles->map(fn ($detalle): array => [
                'parametro_id' => $detalle->parametro_id,
                'parametro_codigo' => $detalle->parametro?->codigo,
                'parametro_nombre' => $detalle->parametro?->nombre,
                'unidad' => $detalle->parametro?->unidad,
                'valor' => (float) $detalle->valor,
                'origen' => $detalle->origen,
                'confianza_ocr' => $detalle->confianza_ocr,
                'cumple' => $detalle->cumple,
            ])->all(),
        ];
    }
}
