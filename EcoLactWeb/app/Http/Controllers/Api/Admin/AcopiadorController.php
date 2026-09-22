<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Acopiador\AcopiadoresFiltro;
use App\Application\Acopiador\Contracts\AcopiadoresListadoQuery;
use App\Application\Acopiador\ObtenerAcopiador;
use App\Application\Acopiador\ObtenerZonaHistorialAcopiador;
use App\Application\Acopio\ObtenerEntregasAcopiador;
use App\Domain\Acopiador\Acopiador;
use App\Domain\Acopiador\AcopiadorZonaHistorial;
use App\Domain\Entrega\Entrega;
use App\Http\Controllers\Api\Concerns\PaginaFilas;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class AcopiadorController extends Controller
{
    use PaginaFilas;

    public function __construct(
        private readonly ObtenerAcopiador $obtenerAcopiador,
        private readonly AcopiadoresListadoQuery $acopiadoresListadoQuery,
        private readonly ObtenerEntregasAcopiador $obtenerEntregasAcopiador,
        private readonly ObtenerZonaHistorialAcopiador $obtenerZonaHistorialAcopiador,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AcopiadorModel::class);

        $filtro = AcopiadoresFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
            zonaId: $request->filled('zona_id') ? $request->integer('zona_id') : null,
        );

        $datos = $this->acopiadoresListadoQuery->obtener($filtro);
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

    public function show(AcopiadorModel $acopiador): JsonResponse
    {
        Gate::authorize('view', $acopiador);

        return response()->json([
            'data' => [
                'acopiador' => $this->serializarAcopiador($this->obtenerAcopiador->execute($acopiador->id)),
                'entregas' => array_map(
                    $this->serializarEntrega(...),
                    $this->obtenerEntregasAcopiador->execute($acopiador->id),
                ),
                'zona_historial' => array_map(
                    $this->serializarZonaHistorial(...),
                    $this->obtenerZonaHistorialAcopiador->execute($acopiador->id),
                ),
            ],
        ]);
    }

    /** @return array<string, mixed>|null */
    private function serializarAcopiador(?Acopiador $acopiador): ?array
    {
        if ($acopiador === null) {
            return null;
        }

        return [
            'id' => $acopiador->id(),
            'user_id' => $acopiador->userId(),
            'zona_id' => $acopiador->zonaId(),
            'dni' => $acopiador->dni(),
            'nombres' => $acopiador->nombres(),
            'apellidos' => $acopiador->apellidos(),
            'nombre_completo' => $acopiador->nombreCompleto(),
            'telefono' => $acopiador->telefono(),
            'estado' => $acopiador->estado()->value,
            'fecha_ingreso' => $acopiador->fechaIngreso()?->format('Y-m-d'),
        ];
    }

    /** @return array<string, mixed> */
    private function serializarEntrega(Entrega $entrega): array
    {
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

    /** @return array<string, mixed> */
    private function serializarZonaHistorial(AcopiadorZonaHistorial $historial): array
    {
        return [
            'id' => $historial->id(),
            'acopiador_id' => $historial->acopiadorId(),
            'zona_id' => $historial->zonaId(),
            'fecha_inicio' => $historial->fechaInicio()->format(DATE_ATOM),
            'fecha_fin' => $historial->fechaFin()?->format(DATE_ATOM),
            'motivo' => $historial->motivo(),
            'esta_vigente' => $historial->estaVigente(),
        ];
    }
}
