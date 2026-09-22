<?php

namespace App\Http\Controllers\Api\Acopiador;

use App\Application\Recorrido\Contracts\RecorridoVistaQuery;
use App\Application\Recorrido\FinalizarRecorrido;
use App\Application\Recorrido\IdentificarParada;
use App\Application\Recorrido\IniciarRecorrido;
use App\Application\Recorrido\MarcarNoEntrego;
use App\Application\Recorrido\ObtenerRecorridosDelAcopiador;
use App\Application\Recorrido\ParadaVista;
use App\Application\Recorrido\RecorridoVista;
use App\Application\Recorrido\RegistrarAcopioDeRecorrido;
use App\Domain\Recorrido\RecorridoAcopio;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recorrido\IdentificarParadaRequest;
use App\Http\Requests\Recorrido\MarcarNoEntregoRequest;
use App\Http\Requests\Recorrido\RegistrarAcopioRecorridoRequest;
use App\Infrastructure\Persistence\Models\RecorridoAcopioModel;
use App\Infrastructure\Persistence\Models\RecorridoDetalleModel;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Mismo contrato funcional que Web\Acopiador\RecorridoController (mismos
 * Use Cases, misma autorización vía RecorridoAcopioPolicy), en el formato
 * {data: ...} que ya usan el resto de endpoints de este rol. Ver
 * docs/mobile-api/acopiador.md sección "Recorridos de acopio".
 */
final class RecorridoController extends Controller
{
    public function __construct(
        private readonly ObtenerRecorridosDelAcopiador $obtenerRecorridosDelAcopiador,
        private readonly RecorridoVistaQuery $recorridoVistaQuery,
        private readonly IniciarRecorrido $iniciarRecorrido,
        private readonly FinalizarRecorrido $finalizarRecorrido,
        private readonly IdentificarParada $identificarParada,
        private readonly RegistrarAcopioDeRecorrido $registrarAcopioDeRecorrido,
        private readonly MarcarNoEntrego $marcarNoEntrego,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', RecorridoAcopioModel::class);

        $acopiadorId = $request->user()->acopiador?->id;
        $recorridos = $acopiadorId !== null ? $this->obtenerRecorridosDelAcopiador->execute($acopiadorId) : [];

        return response()->json(['data' => collect($recorridos)->map(fn (RecorridoAcopio $r): array => $this->serializarRecorrido($r))->all()]);
    }

    public function show(RecorridoAcopioModel $recorrido): JsonResponse
    {
        Gate::authorize('view', $recorrido);

        return response()->json(['data' => $this->serializarVista($this->recorridoVistaQuery->obtener($recorrido->id))]);
    }

    public function iniciar(Request $request, RecorridoAcopioModel $recorrido): JsonResponse
    {
        Gate::authorize('update', $recorrido);

        $this->iniciarRecorrido->execute($recorrido->id, $request->user()->acopiador->id);

        return response()->json(['data' => $this->serializarVista($this->recorridoVistaQuery->obtener($recorrido->id))]);
    }

    public function finalizar(Request $request, RecorridoAcopioModel $recorrido): JsonResponse
    {
        Gate::authorize('update', $recorrido);

        $this->finalizarRecorrido->execute($recorrido->id, $request->user()->acopiador->id);

        return response()->json(['data' => $this->serializarVista($this->recorridoVistaQuery->obtener($recorrido->id))]);
    }

    public function identificar(IdentificarParadaRequest $request, RecorridoAcopioModel $recorrido): JsonResponse
    {
        Gate::authorize('update', $recorrido);

        $datos = $request->validated();

        try {
            $parada = $this->identificarParada->execute($recorrido->id, $datos['metodo'], $datos['identificador']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['parada_id' => $parada->id()]]);
    }

    public function registrarAcopio(RegistrarAcopioRecorridoRequest $request, RecorridoAcopioModel $recorrido, RecorridoDetalleModel $parada): JsonResponse
    {
        Gate::authorize('update', $recorrido);

        if ($parada->recorrido_id !== $recorrido->id) {
            abort(404);
        }

        $datos = $request->validated();

        try {
            $resultado = $this->registrarAcopioDeRecorrido->execute(
                recorridoDetalleId: $parada->id,
                acopiadorUserId: $request->user()->id,
                litros: (float) $datos['litros'],
                tokenIdempotencia: $datos['token_idempotencia'],
                fechaHoraOrdeño: isset($datos['fecha_hora_ordeño']) ? new DateTimeImmutable($datos['fecha_hora_ordeño']) : null,
                observacion: $datos['observacion'] ?? null,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => [
            'entrega_id' => $resultado->entrega->id(),
            'litros' => $resultado->entrega->litros(),
            'parada' => ['id' => $resultado->parada->id(), 'estado' => $resultado->parada->estado()->value],
        ]], 201);
    }

    public function noEntrego(MarcarNoEntregoRequest $request, RecorridoAcopioModel $recorrido, RecorridoDetalleModel $parada): JsonResponse
    {
        Gate::authorize('update', $recorrido);

        if ($parada->recorrido_id !== $recorrido->id) {
            abort(404);
        }

        try {
            $paradaActualizada = $this->marcarNoEntrego->execute(
                recorridoDetalleId: $parada->id,
                acopiadorUserId: $request->user()->id,
                motivo: $request->validated('motivo'),
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => [
            'parada' => ['id' => $paradaActualizada->id(), 'estado' => $paradaActualizada->estado()->value, 'observacion' => $paradaActualizada->observacion()],
        ]]);
    }

    /** @return array<string, mixed> */
    private function serializarRecorrido(RecorridoAcopio $recorrido): array
    {
        return [
            'id' => $recorrido->id(),
            'ruta_id' => $recorrido->rutaId(),
            'fecha_operativa' => $recorrido->fechaOperativa()->format('Y-m-d'),
            'estado' => $recorrido->estado()->value,
            'iniciado_en' => $recorrido->iniciadoEn()?->format(DATE_ATOM),
            'finalizado_en' => $recorrido->finalizadoEn()?->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarVista(RecorridoVista $vista): array
    {
        return [
            'id' => $vista->id,
            'ruta_id' => $vista->rutaId,
            'ruta_nombre' => $vista->rutaNombre,
            'zona_nombre' => $vista->zonaNombre,
            'estado' => $vista->estado,
            'fecha_operativa' => $vista->fechaOperativa,
            'progreso' => $vista->progreso,
            'paradas' => collect($vista->paradas)->map(fn (ParadaVista $p): array => [
                'id' => $p->id,
                'proveedor_id' => $p->proveedorId,
                'proveedor_nombre' => $p->proveedorNombre,
                'proveedor_dni' => $p->proveedorDni,
                'orden_visita' => $p->ordenVisita,
                'latitud' => $p->latitud,
                'longitud' => $p->longitud,
                'referencia_ubicacion' => $p->referenciaUbicacion,
                'estado' => $p->estado,
                'observacion' => $p->observacion,
                'litros_registrados' => $p->litrosRegistrados,
            ])->all(),
        ];
    }
}
