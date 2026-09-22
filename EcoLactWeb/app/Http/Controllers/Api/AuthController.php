<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Support\SesionWeb;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    public function __construct(
        private readonly SesionWeb $sesion,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $credenciales = $request->safe()->only(['username', 'password']);
        $clave = 'login:'.hash('sha256', Str::transliterate(Str::lower($credenciales['username'])).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($clave, 5)) {
            throw ValidationException::withMessages([
                'username' => 'Demasiados intentos. Inténtalo nuevamente en '.RateLimiter::availableIn($clave).' segundos.',
            ]);
        }

        $user = User::where('username', $credenciales['username'])->first();

        if ($user === null || ! Hash::check($credenciales['password'], $user->password)) {
            RateLimiter::hit($clave, 60);

            throw ValidationException::withMessages(['username' => 'Usuario o contraseña incorrectos.']);
        }

        $error = $this->sesion->errorAcceso($user);

        if ($error !== null) {
            RateLimiter::hit($clave, 60);

            abort(403, $error);
        }

        RateLimiter::clear($clave);
        $user->forceFill(['ultimo_acceso' => now()])->save();

        $token = $user->createToken($request->userAgent() ?: 'mobile-app')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => $this->usuario($user),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->usuario($request->user())]);
    }

    /** @return array{id: int, username: string, role: ?string, estado: string, debe_cambiar_password: bool, ultimo_acceso: ?string} */
    private function usuario(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'role' => $user->role?->nombre,
            'estado' => $user->estado,
            'debe_cambiar_password' => $user->debe_cambiar_password,
            'ultimo_acceso' => $user->ultimo_acceso?->toIso8601String(),
        ];
    }
}
