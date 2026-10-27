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
 * Con `Idempotency-Key`, la petición reserva la clave del usuario DENTRO de la
 * misma transacción que la operación y, si termina bien, guarda su respuesta
 * en esa transacción: la operación y su respuesta se confirman juntas o no se
 * confirma ninguna. Un reintento con la misma clave recibe la respuesta
 * guardada sin volver a ejecutar la operación. Sin la cabecera la petición
 * pasa tal cual (la web no la envía).
 *
 *   - misma clave en curso                  -> el reintento espera el bloqueo
 *     del índice único y, cuando la primera termina, recibe su respuesta,
 *   - misma clave usada en otra operación   -> 422 (la operación es el método
 *     más la ruta concreta: finalizar el lote 5 no es finalizar el lote 6),
 *   - la operación falla (422, 403, 500...) -> no queda clave: corregir y
 *     reintentar con la misma clave vuelve a ejecutar la operación.
 *
 * No se compara el cuerpo: la app reconstruye la petición en cada reintento
 * (p. ej. la compra viaja con o sin la ruta de la boleta ya subida) y una
 * huella del cuerpo rechazaría reintentos legítimos de operaciones que el
 * servidor sí registró.
 */
final class IdempotenciaApi
{
    private const TABLA = 'api_idempotencia';

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

        $operacion = mb_substr($request->method().' '.$request->path(), 0, 150);
        $existente = $this->buscar($user->id, $clave);

        // Con la reserva dentro de la transacción solo quedan filas con
        // respuesta; una sin respuesta es una reserva huérfana del modelo
        // anterior (reservaba fuera de la transacción).
        if ($existente !== null && $existente->estado_http === null) {
            DB::table(self::TABLA)->where('id', $existente->id)->delete();
            $existente = null;
        }

        if ($existente !== null) {
            return $this->repetir($existente, $operacion);
        }

        try {
            return DB::transaction(function () use ($next, $request, $user, $clave, $operacion): Response {
                // Si otra petición con la misma clave está en curso, este
                // INSERT espera su bloqueo y falla cuando ella confirma.
                $id = DB::table(self::TABLA)->insertGetId([
                    'user_id' => $user->id,
                    'clave' => $clave,
                    'ruta' => $operacion,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $response = $next($request);

                if ($response->isSuccessful()) {
                    DB::table(self::TABLA)->where('id', $id)->update([
                        'estado_http' => $response->getStatusCode(),
                        'respuesta' => (string) $response->getContent(),
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table(self::TABLA)->where('id', $id)->delete();
                }

                return $response;
            });
        } catch (UniqueConstraintViolationException $e) {
            $existente = $this->buscar($user->id, $clave);

            if ($existente === null) {
                // La violación no fue de la clave sino de la propia operación.
                throw $e;
            }

            return $this->repetir($existente, $operacion);
        }
    }

    private function buscar(int $userId, string $clave): ?object
    {
        return DB::table(self::TABLA)->where('user_id', $userId)->where('clave', $clave)->first();
    }

    private function repetir(object $existente, string $operacion): Response
    {
        if ($existente->estado_http === null) {
            return response()->json(['message' => 'Esta operación todavía se está procesando. Intenta de nuevo en unos segundos.'], 409);
        }

        if ($existente->ruta !== $operacion) {
            return response()->json(['message' => 'La clave de idempotencia ya se usó en otra operación.'], 422);
        }

        return response((string) $existente->respuesta, (int) $existente->estado_http, [
            'Content-Type' => 'application/json',
            'Idempotent-Replayed' => 'true',
        ]);
    }
}
