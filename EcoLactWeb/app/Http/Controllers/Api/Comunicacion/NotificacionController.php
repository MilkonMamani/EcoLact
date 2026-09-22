<?php

namespace App\Http\Controllers\Api\Comunicacion;

use App\Application\Comunicacion\MarcarNotificacionComoLeida;
use App\Application\Comunicacion\ObtenerNotificaciones;
use App\Domain\Comunicacion\Notificacion;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\NotificacionModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Compartido para los 6 roles: ObtenerNotificaciones siempre consulta las
 * notificaciones del usuario AUTENTICADO (nunca un user_id arbitrario del
 * cliente), por lo que index() no necesita Policy.
 */
final class NotificacionController extends Controller
{
    public function __construct(
        private readonly ObtenerNotificaciones $obtenerNotificaciones,
        private readonly MarcarNotificacionComoLeida $marcarNotificacionComoLeida,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $notificaciones = $this->obtenerNotificaciones->execute($request->user()->id);

        return response()->json(['data' => array_map($this->serializar(...), $notificaciones)]);
    }

    public function marcarComoLeida(Request $request, NotificacionModel $notificacion): JsonResponse
    {
        Gate::authorize('update', $notificacion);

        $this->marcarNotificacionComoLeida->execute($notificacion->id, $request->user()->id);

        return response()->json(['message' => 'Notificación marcada como leída.']);
    }

    /** @return array<string, mixed> */
    private function serializar(Notificacion $notificacion): array
    {
        return [
            'id' => $notificacion->id(),
            'tipo' => $notificacion->tipo(),
            'titulo' => $notificacion->titulo(),
            'mensaje' => $notificacion->mensaje(),
            'referencia_tipo' => $notificacion->referenciaTipo(),
            'referencia_id' => $notificacion->referenciaId(),
            'leido' => $notificacion->leido(),
            'fecha_lectura' => $notificacion->fechaLectura()?->format(DATE_ATOM),
        ];
    }
}
