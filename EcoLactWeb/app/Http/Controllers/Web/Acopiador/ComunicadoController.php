<?php

namespace App\Http\Controllers\Web\Acopiador;

use App\Application\Comunicacion\MarcarComunicadoComoLeido;
use App\Application\Comunicacion\ObtenerComunicado;
use App\Application\Comunicacion\ObtenerComunicados;
use App\Application\Comunicacion\ObtenerLecturaComunicados;
use App\Domain\Comunicacion\Comunicado;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ComunicadoModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Autoservicio del ACOPIADOR: siempre los comunicados dirigidos al propio
 * usuario autenticado (ObtenerComunicados trata null como "todos", uso
 * exclusivo de ADMINISTRADOR; aquí nunca se le pasa null).
 */
final class ComunicadoController extends Controller
{
    public function __construct(
        private readonly ObtenerComunicados $obtenerComunicados,
        private readonly ObtenerComunicado $obtenerComunicado,
        private readonly ObtenerLecturaComunicados $obtenerLecturaComunicados,
        private readonly MarcarComunicadoComoLeido $marcarComunicadoComoLeido,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ComunicadoModel::class);

        $comunicados = $this->obtenerComunicados->execute($request->user()->id);
        usort($comunicados, static fn (Comunicado $a, Comunicado $b): int => $b->fechaPublicacion() <=> $a->fechaPublicacion());

        $lecturas = $this->obtenerLecturaComunicados->execute($request->user()->id);

        $kpis = [
            'total' => count($comunicados),
            'noLeidos' => collect($comunicados)->filter(fn (Comunicado $c): bool => ! ($lecturas[$c->id()] ?? false))->count(),
            'ultimo' => $comunicados[0] ?? null,
        ];

        return view('acopiador.comunicados.index', [
            'comunicados' => $comunicados,
            'lecturas' => $lecturas,
            'kpis' => $kpis,
        ]);
    }

    public function show(ComunicadoModel $comunicado): View
    {
        Gate::authorize('view', $comunicado);

        $this->marcarComunicadoComoLeido->execute($comunicado->id, auth()->id());

        return view('acopiador.comunicados.show', [
            'comunicado' => $this->obtenerComunicado->execute($comunicado->id),
        ]);
    }
}
