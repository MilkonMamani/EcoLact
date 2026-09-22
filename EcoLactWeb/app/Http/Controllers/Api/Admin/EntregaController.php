<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Acopio\Contracts\EntregasListadoQuery;
use App\Application\Acopio\EntregasFiltro;
use App\Application\Acopio\ObtenerEntrega;
use App\Domain\Entrega\Entrega;
use App\Http\Controllers\Api\Concerns\PaginaFilas;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\EntregaModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class EntregaController extends Controller
{
    use PaginaFilas;

    public function __construct(
        private readonly ObtenerEntrega $obtenerEntrega,
        private readonly EntregasListadoQuery $entregasListadoQuery,
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
                'acopiadores' => $datos->acopiadores,
                'filas' => $filas,
            ],
            'meta' => $meta,
        ]);
    }

    public function show(EntregaModel $entrega): JsonResponse
    {
        Gate::authorize('view', $entrega);

        return response()->json([
            'data' => $this->serializar($this->obtenerEntrega->execute($entrega->id)),
        ]);
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
            'registrado_por_user_id' => $entrega->registradoPorUserId(),
            'fecha_hora' => $entrega->fechaHora()->format(DATE_ATOM),
            'litros' => $entrega->litros(),
            'metodo_identificacion' => $entrega->metodoIdentificacion(),
            'estado' => $entrega->estado()->value,
            'observacion' => $entrega->observacion(),
        ];
    }
}
