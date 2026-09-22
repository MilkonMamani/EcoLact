<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a uno o varios roles, comparando siempre por el
 * nombre estable del rol (roles.nombre), nunca por role_id numérico.
 *
 * Uso: ->middleware('role:ADMINISTRADOR') o ->middleware('role:ADMINISTRADOR,ACOPIADOR')
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if (! $user->tieneRol(...$roles)) {
            abort(403, 'No tiene permisos para acceder a este recurso.');
        }

        return $next($request);
    }
}
