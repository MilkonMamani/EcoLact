<?php

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Support\SesionWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class LoginController extends Controller
{
    public function __construct(
        private readonly SesionWeb $sesion,
    ) {}

    public function show(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credenciales = $request->safe()->only(['username', 'password']);
        $clave = 'login:'.hash('sha256', Str::transliterate(Str::lower($credenciales['username'])).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($clave, 5)) {
            throw ValidationException::withMessages([
                'username' => 'Demasiados intentos. Inténtalo nuevamente en '.RateLimiter::availableIn($clave).' segundos.',
            ]);
        }

        if (! Auth::guard('web')->attempt($credenciales, $request->boolean('remember'))) {
            RateLimiter::hit($clave, 60);

            throw ValidationException::withMessages(['username' => 'Usuario o contraseña incorrectos.']);
        }

        $request->session()->regenerate();
        $error = $this->sesion->errorAcceso(Auth::guard('web')->user());

        if ($error !== null) {
            RateLimiter::hit($clave, 60);
            $this->sesion->cerrar($request);

            throw ValidationException::withMessages(['username' => $error]);
        }

        RateLimiter::clear($clave);
        $request->session()->forget('url.intended');

        return $this->sesion->redirigir($request);
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->sesion->cerrar($request);

        return redirect()->route('login')->with('success', 'Sesión cerrada correctamente.');
    }
}
