<?php

namespace App\Http\Controllers\Web\Comunicacion;

use App\Application\Comunicacion\MarcarNotificacionComoLeida;
use App\Application\Comunicacion\ObtenerNotificaciones;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\NotificacionModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * ObtenerNotificaciones siempre consulta las notificaciones del usuario
 * AUTENTICADO (nunca un user_id arbitrario del cliente), por lo que index()
 * no necesita Policy: no hay recurso ajeno al que se pueda acceder.
 */
final class NotificacionController extends Controller
{
    public function __construct(
        private readonly ObtenerNotificaciones $obtenerNotificaciones,
        private readonly MarcarNotificacionComoLeida $marcarNotificacionComoLeida,
    ) {}

    /**
     * Vista futura (Etapa 10.3): notificaciones.index
     */
    public function index(Request $request): View
    {
        return view('notificaciones.index', [
            'notificaciones' => $this->obtenerNotificaciones->execute($request->user()->id),
        ]);
    }

    public function marcarComoLeida(Request $request, NotificacionModel $notificacion): RedirectResponse
    {
        Gate::authorize('update', $notificacion);

        $this->marcarNotificacionComoLeida->execute($notificacion->id, $request->user()->id);

        return redirect()->back()->with('success', 'Notificación marcada como leída.');
    }
}
