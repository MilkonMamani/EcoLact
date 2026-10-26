<?php

namespace App\Http\Controllers\Web\Planta;

use App\Application\Produccion\Contracts\RecetasListadoQuery;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\RecetaModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Portal del ENCARGADO_PLANTA: consulta de recetas en SOLO LECTURA. Las
 * recetas las configura el ADMINISTRADOR (RecetaPolicy).
 */
final class RecetaController extends Controller
{
    public function __construct(
        private readonly RecetasListadoQuery $recetasListadoQuery,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', RecetaModel::class);

        return view('planta.recetas.index', [
            'datos' => $this->recetasListadoQuery->obtener([
                'producto_id' => $request->integer('producto') ?: null,
                'estado' => $request->query('estado'),
                'busqueda' => $request->query('q'),
                'solo_vigente' => true,
            ]),
        ]);
    }
}
