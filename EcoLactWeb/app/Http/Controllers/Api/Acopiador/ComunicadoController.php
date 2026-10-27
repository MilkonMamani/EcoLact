<?php

namespace App\Http\Controllers\Api\Acopiador;

use App\Application\Comunicacion\MarcarComunicadoComoLeido;
use App\Application\Comunicacion\ObtenerComunicado;
use App\Application\Comunicacion\ObtenerComunicados;
use App\Application\Comunicacion\ObtenerLecturaComunicados;
use App\Domain\Comunicacion\Comunicado;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ComunicadoModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Comunicados del acopiador. Igual que en la API del proveedor, abrir el
 * detalle lo marca leído; el listado trae `leido` para el filtro "No leídos".
 */
final class ComunicadoController extends Controller
{
    public function __construct(
        private readonly ObtenerComunicados $obtenerComunicados,
        private readonly ObtenerComunicado $obtenerComunicado,
        private readonly ObtenerLecturaComunicados $obtenerLecturaComunicados,
        private readonly MarcarComunicadoComoLeido $marcarComunicadoComoLeido,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ComunicadoModel::class);

        $comunicados = $this->obtenerComunicados->execute($request->user()->id);
        usort($comunicados, static fn (Comunicado $a, Comunicado $b): int => $b->fechaPublicacion() <=> $a->fechaPublicacion());

        $lecturas = $this->obtenerLecturaComunicados->execute($request->user()->id);

        return response()->json(['data' => array_map(
            fn (Comunicado $c): array => [...$this->serializar($c), 'leido' => $lecturas[$c->id()] ?? false],
            $comunicados,
        )]);
    }

    public function show(Request $request, ComunicadoModel $comunicado): JsonResponse
    {
        Gate::authorize('view', $comunicado);

        $this->marcarComunicadoComoLeido->execute($comunicado->id, $request->user()->id);

        return response()->json(['data' => [...$this->serializar($this->obtenerComunicado->execute($comunicado->id)), 'leido' => true]]);
    }

    /** @return array<string, mixed>|null */
    private function serializar(?Comunicado $comunicado): ?array
    {
        if ($comunicado === null) {
            return null;
        }

        return [
            'id' => $comunicado->id(),
            'titulo' => $comunicado->titulo(),
            'contenido' => $comunicado->contenido(),
            'tipo' => $comunicado->tipo(),
            'alcance' => $comunicado->alcance(),
            'fecha_publicacion' => $comunicado->fechaPublicacion()->format(DATE_ATOM),
            'fecha_evento' => $comunicado->fechaEvento()?->format(DATE_ATOM),
            'estado' => $comunicado->estado(),
        ];
    }
}
