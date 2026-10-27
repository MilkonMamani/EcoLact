<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Escrituras seguras ante reintentos de la app móvil (tabla api_idempotencia).
 *
 * Con `Idempotency-Key`, la primera petición reserva la clave del usuario y,
 * si termina bien, guarda su respuesta; un reintento con la misma clave recibe
 * esa respuesta sin volver a ejecutar la operación. Sin la cabecera la
 * petición pasa tal cual (la web no la envía).
 *
 *   - misma clave en curso                  -> 409 (la app reintenta después),
 *   - misma clave usada en otra operación   -> 422,
 *   - la operación falla (422, 403, 500...) -> se libera la clave: corregir y
 *     reintentar con la misma clave vuelve a ejecutar la operación.
 */
final class IdempotenciaApi
{
    private const TABLA = 'api_idempotencia';

    /** Una reserva sin respuesta más vieja que esto quedó huérfana (proceso caído). */
    private const SEGUNDOS_RESERVA = 120;

    public function handle(Request $request, Closure $next): Response
    {
        $clave = trim((string) $request->header('Idempotency-Key'));
        $user = $request->user();

        if ($clave === '' || $user === null || $request->isMethodSafe()) {
            return $next($request);
        }

        if (mb_strlen($clave) > 100) {
            return response()->json(['message' => 'La clave de idempotencia no es válida.'], 422);
        }

        $ruta = $request->route()?->getName() ?? $request->path();
        $existente = $this->buscar($user->id, $clave);

        if ($existente !== null && $existente->estado_http === null && strtotime((string) $existente->updated_at) < time() - self::SEGUNDOS_RESERVA) {
            DB::table(self::TABLA)->where('id', $existente->id)->delete();
            $existente = null;
        }

        if ($existente !== null) {
            return $this->repetir($existente, $ruta);
        }

        try {
            $id = DB::table(self::TABLA)->insertGetId([
                'user_id' => $user->id,
                'clave' => $clave,
                'ruta' => $ruta,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Otra petición con la misma clave la reservó en este instante.
            return $this->repetir($this->buscar($user->id, $clave), $ruta);
        }

        try {
            $response = DB::transaction(function () use ($next, $request, $id): Response {
                $response = $next($request);
                if ($response->isSuccessful()) {
                    DB::table(self::TABLA)->where('id', $id)->update([
                        'estado_http' => $response->getStatusCode(),
                        'respuesta' => (string) $response->getContent(),
                        'updated_at' => now(),
                    ]);
                }

                return $response;
            });
        } catch (\Throwable $e) {
            DB::table(self::TABLA)->where('id', $id)->delete();

            throw $e;
        }

        if (! $response->isSuccessful()) {
            DB::table(self::TABLA)->where('id', $id)->delete();
        }

        return $response;
    }

    private function buscar(int $userId, string $clave): ?object
    {
        return DB::table(self::TABLA)->where('user_id', $userId)->where('clave', $clave)->first();
    }

    private function repetir(?object $existente, string $ruta): Response
    {
        if ($existente === null || $existente->estado_http === null) {
            return response()->json(['message' => 'Esta operación todavía se está procesando. Intenta de nuevo en unos segundos.'], 409);
        }

        if ($existente->ruta !== $ruta) {
            return response()->json(['message' => 'La clave de idempotencia ya se usó en otra operación.'], 422);
        }

        return response((string) $existente->respuesta, (int) $existente->estado_http, [
            'Content-Type' => 'application/json',
            'Idempotent-Replayed' => 'true',
        ]);
    }
}
