<?php

namespace App\Http\Controllers\Api\Calidad;

use App\Application\Acopio\Contracts\EntregasListadoQuery;
use App\Application\Acopio\EntregasFiltro;
use App\Application\Acopio\ObtenerEntrega;
use App\Application\Calidad\RegistrarEntregaMuestreada;
use App\Domain\Entrega\Entrega;
use App\Http\Controllers\Api\Concerns\PaginaFilas;
use App\Http\Controllers\Controller;
use App\Http\Requests\Entrega\StoreEntregaRequest;
use App\Infrastructure\Persistence\Models\EntregaModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Entregas de CUALQUIER zona, solo lectura para el control de calidad
 * (mismo alcance que Web\Analista\EntregaController) — salvo `store`, la
 * única escritura: el MUESTREO DIRECTO, cuando el analista toma la muestra
 * directamente del proveedor antes del acopio normal (inspección sorpresa,
 * ver RegistrarEntregaMuestreada). La entrega queda con su muestra
 * SELECCIONADA lista para analizar.
 */
final class EntregaController extends Controller
{
    use PaginaFilas;

    public function __construct(
        private readonly EntregasListadoQuery $entregasListadoQuery,
        private readonly ObtenerEntrega $obtenerEntrega,
        private readonly RegistrarEntregaMuestreada $registrarEntregaMuestreada,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EntregaModel::class);

        $filtro = EntregasFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            zonaId: $request->filled('zona_id') ? $request->integer('zona_id') : null,
            acopiadorId: $request->filled('acopiador_id') ? $request->integer('acopiador_id') : null,
            estado: $request->string('estado')->toString() ?: null,
            fecha: $request->string('fecha')->toString() ?: null,
            desde: $request->string('desde')->toString() ?: null,
            hasta: $request->string('hasta')->toString() ?: null,
        );

        $datos = $this->entregasListadoQuery->obtener($filtro);
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

    public function show(EntregaModel $entrega): JsonResponse
    {
        Gate::authorize('view', $entrega);

        return response()->json(['data' => $this->serializar($this->obtenerEntrega->execute($entrega->id))]);
    }

    public function store(StoreEntregaRequest $request): JsonResponse
    {
        Gate::authorize('create', EntregaModel::class);

        $datos = $request->validated();

        $entrega = $this->registrarEntregaMuestreada->execute(
            metodoIdentificacion: $datos['metodo_identificacion'],
            identificador: $datos['identificador'],
            analistaUserId: $request->user()->id,
            litros: (float) $datos['litros'],
            observacion: $datos['observacion'] ?? null,
        );

        return response()->json(['data' => $this->serializar($entrega)], 201);
    }

    /** @return array<string, mixed>|null */
    private function serializar(?Entrega $entrega): ?array
    {
        if ($entrega === null) {
            return null;
        }

        return [
            'id' => $entrega->id(),
            'proveedor_id' => $entrega->proveedorId(),
            'acopiador_id' => $entrega->acopiadorId(),
            'zona_id' => $entrega->zonaId(),
            'fecha_hora' => $entrega->fechaHora()->format(DATE_ATOM),
            'litros' => $entrega->litros(),
            'metodo_identificacion' => $entrega->metodoIdentificacion(),
            'estado' => $entrega->estado()->value,
            'observacion' => $entrega->observacion(),
        ];
    }
}
