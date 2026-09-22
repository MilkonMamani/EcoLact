<?php

namespace App\Http\Controllers\Api\Calidad;

use App\Application\Comunicacion\CrearComunicado;
use App\Application\Comunicacion\ObtenerComunicado;
use App\Application\Comunicacion\ObtenerComunicados;
use App\Domain\Comunicacion\Comunicado;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comunicacion\StoreComunicadoRequest;
use App\Infrastructure\Persistence\Models\ComunicadoModel;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * CALIDAD, a diferencia de ACOPIADOR/PROVEEDOR, sí puede crear comunicados
 * (propone capacitaciones), reutilizando el mismo CrearComunicado que
 * ADMINISTRADOR.
 */
final class ComunicadoController extends Controller
{
    public function __construct(
        private readonly ObtenerComunicados $obtenerComunicados,
        private readonly ObtenerComunicado $obtenerComunicado,
        private readonly CrearComunicado $crearComunicado,
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

    public function store(StoreComunicadoRequest $request): JsonResponse
    {
        Gate::authorize('create', ComunicadoModel::class);

        $datos = $request->validated();

        $comunicado = $this->crearComunicado->execute(
            titulo: $datos['titulo'],
            contenido: $datos['contenido'],
            tipo: $datos['tipo'],
            alcance: $datos['alcance'],
            creadoPorUserId: $request->user()->id,
            destinatarioUserIds: $datos['destinatarios'] ?? [],
            zonaId: isset($datos['zona_id']) ? (int) $datos['zona_id'] : null,
            fechaEvento: isset($datos['fecha_evento']) ? new DateTimeImmutable($datos['fecha_evento']) : null,
            estado: $datos['estado'] ?? 'PUBLICADO',
            fechaPublicacion: isset($datos['fecha_publicacion']) ? new DateTimeImmutable($datos['fecha_publicacion']) : null,
        );

        return response()->json(['data' => $this->serializar($comunicado)], 201);
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
            'zona_id' => $comunicado->zonaId(),
            'fecha_publicacion' => $comunicado->fechaPublicacion()->format(DATE_ATOM),
            'fecha_evento' => $comunicado->fechaEvento()?->format(DATE_ATOM),
            'estado' => $comunicado->estado(),
        ];
    }
}
