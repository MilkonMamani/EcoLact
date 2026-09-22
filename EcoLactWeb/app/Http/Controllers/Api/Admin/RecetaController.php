<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Produccion\Contracts\RecetasListadoQuery;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\RecetaModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Consulta de recetas para el cliente móvil (solo lectura). Las recetas se
 * configuran desde el panel web; guardarlas nunca mueve inventario.
 */
final class RecetaController extends Controller
{
    public function __construct(
        private readonly RecetasListadoQuery $recetasListadoQuery,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', RecetaModel::class);

        $datos = $this->recetasListadoQuery->obtener([
            'producto_id' => $request->integer('producto') ?: null,
            'estado' => $request->query('estado'),
            'busqueda' => $request->query('q'),
        ]);

        return response()->json(['data' => ['kpis' => $datos->kpis, 'recetas' => $datos->filas]]);
    }
}
