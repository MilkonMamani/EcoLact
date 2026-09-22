<?php

namespace App\Http\Controllers\Api\Acopiador;

use App\Application\Comunicacion\ObtenerComunicado;
use App\Application\Comunicacion\ObtenerComunicados;
use App\Domain\Comunicacion\Comunicado;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ComunicadoModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ComunicadoController extends Controller
{
    public function __construct(
        private readonly ObtenerComunicados $obtenerComunicados,
        private readonly ObtenerComunicado $obtenerComunicado,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ComunicadoModel::class);

        $comunicados = $this->obtenerComunicados->execute($request->user()->id);
        usort($comunicados, static fn (Comunicado $a, Comunicado $b): int => $b->fechaPublicacion() <=> $a->fechaPublicacion());

        return response()->json(['data' => array_map($this->serializar(...), $comunicados)]);
    }

    public function show(ComunicadoModel $comunicado): JsonResponse
    {
        Gate::authorize('view', $comunicado);

        return response()->json(['data' => $this->serializar($this->obtenerComunicado->execute($comunicado->id))]);
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
