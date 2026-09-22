<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea operaciones protegidas para cuentas inactivas. Revisa tanto
 * users.estado como (según el rol) proveedores.estado / acopiadores.estado,
 * ya que DesactivarAcopiador/DesactivarProveedor solo tocan su propia
 * tabla, no la del usuario (ver User::estaOperativo()).
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->estaOperativo()) {
            abort(403, 'Su cuenta está inactiva.');
        }

        return $next($request);
    }
}
