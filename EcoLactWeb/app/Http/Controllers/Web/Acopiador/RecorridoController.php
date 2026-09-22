<?php

namespace App\Http\Controllers\Web\Acopiador;

use App\Application\Recorrido\Contracts\RecorridoVistaQuery;
use App\Application\Recorrido\FinalizarRecorrido;
use App\Application\Recorrido\IdentificarParada;
use App\Application\Recorrido\IniciarRecorrido;
use App\Application\Recorrido\MarcarNoEntrego;
use App\Application\Recorrido\ObtenerRecorridosDelAcopiador;
use App\Application\Recorrido\RegistrarAcopioDeRecorrido;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recorrido\IdentificarParadaRequest;
use App\Http\Requests\Recorrido\MarcarNoEntregoRequest;
use App\Http\Requests\Recorrido\RegistrarAcopioRecorridoRequest;
use App\Infrastructure\Persistence\Models\RecorridoAcopioModel;
use App\Infrastructure\Persistence\Models\RecorridoDetalleModel;
use App\Infrastructure\Persistence\Models\RutaModel;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Pantalla operativa del ACOPIADOR: abrir su recorrido del día, ver el mapa
 * y la lista ordenada de paradas, identificar al proveedor por DNI/QR y
 * registrar el acopio. RecorridoAcopioPolicy garantiza en cada acción que
 * el recorrido pertenece al acopiador autenticado (nunca se confía en el ID
 * de la URL por sí solo).
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

    public function index(): View
    {
        Gate::authorize('viewAny', RecorridoAcopioModel::class);

        $acopiadorId = auth()->user()->acopiador?->id;
        $recorridos = $acopiadorId !== null ? $this->obtenerRecorridosDelAcopiador->execute($acopiadorId) : [];

        $rutasPorId = RutaModel::with('zona')
            ->whereIn('id', array_map(fn ($r) => $r->rutaId(), $recorridos))
            ->get()
            ->keyBy('id');

        return view('acopiador.recorridos.index', ['recorridos' => $recorridos, 'rutasPorId' => $rutasPorId]);
    }

    public function show(RecorridoAcopioModel $recorrido): View
    {
        Gate::authorize('view', $recorrido);

        return view('acopiador.recorridos.show', [
            'vista' => $this->recorridoVistaQuery->obtener($recorrido->id),
            'recorridoId' => $recorrido->id,
        ]);
    }

    public function iniciar(RecorridoAcopioModel $recorrido): RedirectResponse
    {
        Gate::authorize('update', $recorrido);

        try {
            $this->iniciarRecorrido->execute($recorrido->id, auth()->user()->acopiador->id);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('acopiador.recorridos.show', $recorrido->id);
    }

    public function finalizar(RecorridoAcopioModel $recorrido): RedirectResponse
    {
        Gate::authorize('update', $recorrido);

        try {
            $this->finalizarRecorrido->execute($recorrido->id, auth()->user()->acopiador->id);
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('acopiador.recorridos.show', $recorrido->id)->with('success', 'Recorrido finalizado.');
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

        return response()->json(['parada_id' => $parada->id()]);
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
                acopiadorUserId: auth()->id(),
                litros: (float) $datos['litros'],
                tokenIdempotencia: $datos['token_idempotencia'],
                fechaHoraOrdeño: isset($datos['fecha_hora_ordeño']) ? new DateTimeImmutable($datos['fecha_hora_ordeño']) : null,
                observacion: $datos['observacion'] ?? null,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'entrega_id' => $resultado->entrega->id(),
            'litros' => $resultado->entrega->litros(),
            'parada' => [
                'id' => $resultado->parada->id(),
                'estado' => $resultado->parada->estado()->value,
            ],
        ]);
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
                acopiadorUserId: auth()->id(),
                motivo: $request->validated('motivo'),
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'parada' => [
                'id' => $paradaActualizada->id(),
                'estado' => $paradaActualizada->estado()->value,
                'observacion' => $paradaActualizada->observacion(),
            ],
        ]);
    }
}
