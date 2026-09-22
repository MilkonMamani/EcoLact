<?php

namespace App\Http\Controllers\Web\Analista;

use App\Application\Comunicacion\ObtenerComunicado;
use App\Application\Comunicacion\ObtenerComunicados;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ComunicadoModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Autoservicio del rol CALIDAD: siempre los comunicados dirigidos al propio
 * usuario autenticado (igual que Web\Proveedor\ComunicadoController;
 * ObtenerComunicados trata null como "todos", uso exclusivo de
 * ADMINISTRADOR — aquí nunca se le pasa null).
 */
final class ComunicadoController extends Controller
{
    public function __construct(
        private readonly ObtenerComunicados $obtenerComunicados,
        private readonly ObtenerComunicado $obtenerComunicado,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ComunicadoModel::class);

        return view('calidad.comunicados.index', [
            'comunicados' => $this->obtenerComunicados->execute($request->user()->id),
        ]);
    }

    public function show(ComunicadoModel $comunicado): View
    {
        Gate::authorize('view', $comunicado);

        return view('calidad.comunicados.show', [
            'comunicado' => $this->obtenerComunicado->execute($comunicado->id),
        ]);
    }
}
