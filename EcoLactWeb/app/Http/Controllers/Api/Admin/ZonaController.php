<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Zona\Contracts\ZonasListadoQuery;
use App\Application\Zona\ZonasFiltro;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ZonaController extends Controller
{
    public function __construct(
        private readonly ZonasListadoQuery $zonasListadoQuery,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ZonaModel::class);

        $filtro = ZonasFiltro::resolver(
            busqueda: $request->string('q')->toString() ?: null,
            estado: $request->string('estado')->toString() ?: null,
        );

        $datos = $this->zonasListadoQuery->obtener($filtro);

        return response()->json([
            'data' => [
                'kpis' => $datos->kpis,
                'filas' => $datos->filas,
            ],
        ]);
    }
}
