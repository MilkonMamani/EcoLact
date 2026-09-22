<?php

namespace App\Http\Controllers\Api\Acopiador;

use App\Application\Acopio\ObtenerEntrega;
use App\Application\Acopio\ObtenerEntregas;
use App\Application\Acopio\RegistrarEntrega;
use App\Application\Calidad\ObtenerAnalisisCalidad;
use App\Application\Calidad\ObtenerAnalisisPorZona;
use App\Domain\Entrega\Entrega;
use App\Http\Controllers\Controller;
use App\Http\Requests\Entrega\StoreEntregaRequest;
use App\Infrastructure\Persistence\Models\EntregaModel;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class EntregaController extends Controller
{
    public function __construct(
        private readonly RegistrarEntrega $registrarEntrega,
        private readonly ObtenerEntregas $obtenerEntregas,
        private readonly ObtenerEntrega $obtenerEntrega,
        private readonly ObtenerAnalisisCalidad $obtenerAnalisisCalidad,
        private readonly ObtenerAnalisisPorZona $obtenerAnalisisPorZona,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EntregaModel::class);

        $zonaId = $request->user()->acopiador?->zona_id;
        $entregas = $zonaId !== null ? $this->obtenerEntregas->execute($zonaId) : [];
        $resultadosPorEntrega = $zonaId !== null ? $this->resultadosPorEntrega($zonaId) : [];

        $filtradas = collect($entregas)->filter(function (Entrega $entrega) use ($request): bool {
            if ($request->filled('proveedor_id') && $entrega->proveedorId() !== $request->integer('proveedor_id')) {
                return false;
            }

            if ($request->filled('estado') && $entrega->estado()->value !== $request->string('estado')->toString()) {
                return false;
            }

            return true;
        })->sortByDesc(fn (Entrega $e) => $e->fechaHora())->values();

        return response()->json([
            'data' => [
                'kpis' => [
                    'entregas' => $filtradas->count(),
                    'litros' => round((float) $filtradas->sum(fn (Entrega $e): float => $e->litros()), 2),
                    'proveedores' => $filtradas->map(fn (Entrega $e): int => $e->proveedorId())->unique()->count(),
                    'pendientes' => $filtradas->filter(fn (Entrega $e): bool => $e->estado()->value === 'PENDIENTE_ANALISIS')->count(),
                ],
                'filas' => $filtradas->map(fn (Entrega $e): array => [
                    ...$this->serializarEntrega($e),
                    'resultado' => $resultadosPorEntrega[$e->id()] ?? null,
                ])->all(),
            ],
        ]);
    }

    public function show(EntregaModel $entrega): JsonResponse
    {
        Gate::authorize('view', $entrega);

        return response()->json([
            'data' => [
                'entrega' => $this->serializarEntrega($this->obtenerEntrega->execute($entrega->id)),
                'analisis' => $this->obtenerAnalisisCalidad->porEntrega($entrega->id)?->resultado()?->value,
            ],
        ]);
    }

    public function store(StoreEntregaRequest $request): JsonResponse
    {
        Gate::authorize('create', EntregaModel::class);

        $datos = $request->validated();

        $entrega = $this->registrarEntrega->execute(
            metodoIdentificacion: $datos['metodo_identificacion'],
            identificador: $datos['identificador'],
            acopiadorUserId: $request->user()->id,
            litros: (float) $datos['litros'],
            fechaHora: isset($datos['fecha_hora']) ? new DateTimeImmutable($datos['fecha_hora']) : null,
            observacion: $datos['observacion'] ?? null,
        );

        return response()->json(['data' => $this->serializarEntrega($entrega)], 201);
    }

    /** @return array<int, ?string> */
    private function resultadosPorEntrega(int $zonaId): array
    {
        return collect($this->obtenerAnalisisPorZona->execute($zonaId))
            ->mapWithKeys(fn ($analisis) => [$analisis->entregaId() => $analisis->resultado()?->value])
            ->all();
    }

    /** @return array<string, mixed>|null */
    private function serializarEntrega(?Entrega $entrega): ?array
    {
        if ($entrega === null) {
            return null;
        }

        return [
            'id' => $entrega->id(),
            'proveedor_id' => $entrega->proveedorId(),
            'acopiador_id' => $entrega->acopiadorId(),
            'zona_id' => $entrega->zonaId(),
            'registrado_por_user_id' => $entrega->registradoPorUserId(),
            'fecha_hora' => $entrega->fechaHora()->format(DATE_ATOM),
            'litros' => $entrega->litros(),
            'metodo_identificacion' => $entrega->metodoIdentificacion(),
            'estado' => $entrega->estado()->value,
            'observacion' => $entrega->observacion(),
        ];
    }
}
