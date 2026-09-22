<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Comunicacion\ComunicadoDetalleData;
use App\Application\Comunicacion\ComunicadosFiltro;
use App\Application\Comunicacion\Contracts\ComunicadosListadoQuery;
use App\Application\Comunicacion\CrearComunicado;
use App\Application\Comunicacion\ObtenerDetalleComunicado;
use App\Http\Controllers\Api\Concerns\PaginaFilas;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comunicacion\StoreComunicadoRequest;
use App\Infrastructure\Persistence\Models\ComunicadoModel;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

final class ComunicadoController extends Controller
{
    use PaginaFilas;

    public function __construct(
        private readonly ComunicadosListadoQuery $comunicadosListadoQuery,
        private readonly ObtenerDetalleComunicado $obtenerDetalleComunicado,
        private readonly CrearComunicado $crearComunicado,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ComunicadoModel::class);

        $filtro = ComunicadosFiltro::resolver(
            busqueda: $request->query('q'),
            tipo: $request->query('tipo'),
            estado: $request->query('estado'),
            periodo: $request->query('periodo'),
            desde: $request->query('desde'),
            hasta: $request->query('hasta'),
        );

        $datos = $this->comunicadosListadoQuery->obtener($filtro);
        [$filas, $meta] = $this->paginar($datos->filas, $request);

        return response()->json([
            'data' => ['kpis' => $datos->kpis, 'filas' => $filas],
            'meta' => $meta,
        ]);
    }

    public function show(ComunicadoModel $comunicado): JsonResponse
    {
        Gate::authorize('view', $comunicado);

        $detalle = $this->obtenerDetalleComunicado->execute($comunicado->id);

        if ($detalle === null) {
            abort(404);
        }

        return response()->json(['data' => $this->serializarDetalle($detalle)]);
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

        return response()->json([
            'data' => [
                'id' => $comunicado->id(),
                'titulo' => $comunicado->titulo(),
                'tipo' => $comunicado->tipo(),
                'alcance' => $comunicado->alcance(),
                'estado' => $comunicado->estado(),
                'fecha_publicacion' => $comunicado->fechaPublicacion()->format(DATE_ATOM),
            ],
        ], 201);
    }

    /** @return array<string, mixed> */
    private function serializarDetalle(ComunicadoDetalleData $detalle): array
    {
        return [
            'id' => $detalle->comunicado->id(),
            'titulo' => $detalle->comunicado->titulo(),
            'contenido' => $detalle->comunicado->contenido(),
            'tipo' => $detalle->comunicado->tipo(),
            'alcance' => $detalle->comunicado->alcance(),
            'zona_id' => $detalle->comunicado->zonaId(),
            'fecha_publicacion' => $detalle->comunicado->fechaPublicacion()->format(DATE_ATOM),
            'fecha_evento' => $detalle->comunicado->fechaEvento()?->format(DATE_ATOM),
            'estado' => $detalle->comunicado->estado(),
            'destinatarios' => $detalle->destinatarios,
            'leidos' => $detalle->leidos,
            'no_leidos' => $detalle->noLeidos,
        ];
    }
}
