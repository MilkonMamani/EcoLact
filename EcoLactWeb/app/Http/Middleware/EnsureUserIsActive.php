<?php

namespace App\Http\Middleware;

use App\Http\Support\SesionWeb;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controla el acceso de una cuenta desactivada por el administrador, aunque ya
 * tuviera la sesión abierta o un token de la app. Revisa users.estado y, según el
 * rol, el estado de su ficha (ver User::estaOperativo()).
 *
 * - Trabajador o proveedor desactivado (User::estaEnSoloConsulta()): solo consulta.
 *   Puede ver (GET/HEAD) todo lo que hizo antes; cualquier acción que registre,
 *   edite o elimine responde 403 sin cortarle la sesión.
 * - Cualquier otra cuenta no operativa: 403 "Acceso no permitido" y se corta el
 *   acceso (en web cierra la sesión; en la app revoca el token usado).
 */
class EnsureUserIsActive
{
    public function __construct(private readonly SesionWeb $sesion) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->estaOperativo()) {
            return $next($request);
        }

        if ($user->estaEnSoloConsulta()) {
            if ($request->isMethodSafe()) {
                return $next($request);
            }

            abort(403, SesionWeb::MENSAJE_SOLO_CONSULTA);
        }

        if ($request->expectsJson()) {
            $token = $user->currentAccessToken();

            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }
        } elseif ($request->hasSession()) {
            $this->sesion->cerrar($request);
        }

        abort(403, SesionWeb::MENSAJE_CUENTA_INACTIVA);
    }
}
