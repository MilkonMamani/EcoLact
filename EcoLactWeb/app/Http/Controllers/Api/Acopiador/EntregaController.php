<?php

namespace App\Http\Controllers\Api\Acopiador;

use App\Application\Acopio\ObtenerEntrega;
use App\Application\Acopio\ObtenerEntregas;
use App\Application\Acopio\RegistrarEntrega;
use App\Domain\Entrega\Entrega;
use App\Http\Controllers\Api\Acopiador\Concerns\SerializaEntregasAcopiador;
use App\Http\Controllers\Controller;
use App\Http\Requests\Entrega\StoreEntregaMovilRequest;
use App\Http\Support\FechaEventoMovil;
use App\Infrastructure\Persistence\Models\EntregaModel;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class EntregaController extends Controller
{
    use SerializaEntregasAcopiador;

    public function __construct(
        private readonly RegistrarEntrega $registrarEntrega,
        private readonly ObtenerEntregas $obtenerEntregas,
        private readonly ObtenerEntrega $obtenerEntrega,
    ) {}

    /**
     * Entregas de la zona del acopiador. `desde` (Y-m-d, opcional) acota el
     * historial que el teléfono guarda para trabajar sin conexión.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', EntregaModel::class);

        $request->validate(['desde' => ['nullable', 'date']]);

        $zonaId = $request->user()->acopiador?->zona_id;
        $entregas = $zonaId !== null ? $this->obtenerEntregas->execute($zonaId) : [];
        $desde = $request->filled('desde') ? new DateTimeImmutable($request->string('desde')->toString().' 00:00:00') : null;

        $filtradas = collect($entregas)->filter(function (Entrega $entrega) use ($request, $desde): bool {
            if ($request->filled('proveedor_id') && $entrega->proveedorId() !== $request->integer('proveedor_id')) {
                return false;
            }

            if ($request->filled('estado') && $entrega->estado()->value !== $request->string('estado')->toString()) {
                return false;
            }

            return $desde === null || $entrega->fechaHora() >= $desde;
        })->sortByDesc(fn (Entrega $e) => $e->fechaHora())->values();

        return response()->json([
            'data' => [
                'kpis' => [
                    'entregas' => $filtradas->count(),
                    'litros' => round((float) $filtradas->sum(fn (Entrega $e): float => $e->litros()), 2),
                    'proveedores' => $filtradas->map(fn (Entrega $e): int => $e->proveedorId())->unique()->count(),
                    'pendientes' => $filtradas->filter(fn (Entrega $e): bool => $e->estado()->value === 'PENDIENTE_ANALISIS')->count(),
                ],
                'filas' => $this->serializarEntregas($filtradas->all()),
            ],
        ]);
    }

    public function show(EntregaModel $entrega): JsonResponse
    {
        Gate::authorize('view', $entrega);

        $fila = $this->serializarEntrega($this->obtenerEntrega->execute($entrega->id));

        return response()->json([
            'data' => [
                'entrega' => $fila,
                'analisis' => $fila['resultado'],
            ],
        ]);
    }

    /**
     * Registro desde el móvil, en línea o desde la cola offline:
     * - `Idempotency-Key` se usa como token de la entrega: un reintento con
     *   la misma clave devuelve la entrega ya creada (nunca la duplica);
     * - `fecha_hora_evento` (o `fecha_hora`, versiones anteriores) conserva la
     *   hora real del acopio en `entregas.fecha_hora`; `created_at` queda como
     *   hora de llegada al servidor (sincronización);
     * - un error de negocio (proveedor inactivo, de otra zona…) responde 422,
     *   para que la cola offline lo marque "requiere revisión" sin reintentar.
     */
    public function store(StoreEntregaMovilRequest $request): JsonResponse
    {
        Gate::authorize('create', EntregaModel::class);

        $datos = $request->validated();

        try {
            $entrega = $this->registrarEntrega->execute(
                metodoIdentificacion: $datos['metodo_identificacion'],
                identificador: $datos['identificador'],
                acopiadorUserId: $request->user()->id,
                litros: (float) $datos['litros'],
                // Hora real del acopio (teléfono); sin ella, la del servidor (clientes antiguos).
                fechaHora: FechaEventoMovil::parse($request->fechaHoraEvento()),
                observacion: $datos['observacion'] ?? null,
                tokenIdempotencia: $datos['idempotency_key'] ?? null,
            );
        } catch (DomainException|InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->serializarEntrega($entrega)], 201);
    }
}
