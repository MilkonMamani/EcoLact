<?php

namespace App\Http\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Coordinación de sesión y navegación Web. Reutiliza la misma definición
 * de usuario operativo que EnsureUserIsActive, sin reglas nuevas de negocio.
 */
final class SesionWeb
{
    /** Mensaje único para toda cuenta desactivada por el administrador (web y app). */
    public const MENSAJE_CUENTA_INACTIVA = 'Acceso no permitido: tu cuenta está desactivada. Comunícate con el administrador.';

    /** Trabajador o proveedor desactivado: entra, pero solo puede consultar. */
    public const MENSAJE_SOLO_CONSULTA = 'Tu cuenta está desactivada: solo puedes consultar tu historial. Comunícate con el administrador para reactivarla.';

    public function destino(User $user): ?string
    {
        return match ($user->role?->nombre) {
            User::ROL_ADMINISTRADOR => 'admin.dashboard',
            User::ROL_ACOPIADOR => 'acopiador.dashboard',
            User::ROL_PROVEEDOR => 'proveedor.dashboard',
            User::ROL_ENCARGADO_PLANTA => 'planta.dashboard',
            User::ROL_VENTAS => 'ventas.dashboard',
            User::ROL_CALIDAD => 'calidad.dashboard',
            default => null,
        };
    }

    public function errorAcceso(User $user): ?string
    {
        if ($this->destino($user) === null) {
            return 'Tu cuenta no tiene un rol válido. Comunícate con el administrador.';
        }

        if (! $user->estaOperativo() && ! $user->estaEnSoloConsulta()) {
            return self::MENSAJE_CUENTA_INACTIVA;
        }

        return null;
    }

    public function cerrar(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function redirigir(Request $request): RedirectResponse
    {
        $user = Auth::guard('web')->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        $error = $this->errorAcceso($user);

        if ($error !== null) {
            $this->cerrar($request);

            return redirect()->route('login')->withErrors(['username' => $error]);
        }

        return redirect()->route($this->destino($user));
    }
}
