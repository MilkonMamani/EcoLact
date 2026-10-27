<?php

namespace App\Http\Middleware;

use App\Application\Pago\EstadoAsistenciaActual;
use App\Http\Support\FechaEventoMovil;
use Closure;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Regla global de operación (alias `requiere.asistencia`): el personal sin
 * asistencia válida ese día puede CONSULTAR, pero no OPERAR. Se aplica solo a
 * las rutas que registran o modifican datos de trabajo (ver routes/api.php y
 * routes/web.php); sesión, perfil, Mi QR, notificaciones y cálculos de solo
 * lectura (previsualizar, requerimientos) quedan libres.
 *
 * Parámetros: nombres de los campos del request que traen la hora REAL del
 * evento registrado sin conexión (p. ej. `requiere.asistencia:fecha_hora_evento,fecha_seleccion`).
 * Si viene, se evalúa la asistencia de ESE día (no la del momento de la
 * sincronización); una hora futura se trata como "ahora". Sin campo, se evalúa hoy.
 *
 * La decisión es de EstadoAsistenciaActual; aquí solo se responde:
 *   JSON -> 403 {message, code: ASISTENCIA_REQUERIDA, asistencia}
 *   web  -> vuelve al formulario con el mensaje (sin perder lo escrito).
 */
final class RequiereAsistencia
{
    public const CODIGO = 'ASISTENCIA_REQUERIDA';

    public const MENSAJE = 'Debes registrar tu asistencia antes de realizar operaciones.';

    public function __construct(private readonly EstadoAsistenciaActual $estadoAsistencia) {}

    public function handle(Request $request, Closure $next, string ...$camposEvento): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $estado = $this->estadoAsistencia->paraUsuario($user->id, $this->momentoDelEvento($request, $camposEvento));

        if ($estado->puedeOperar()) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => self::MENSAJE,
                'code' => self::CODIGO,
                'asistencia' => $estado->toArray(),
            ], 403);
        }

        return redirect()->back()->withInput()->with('error', 'Registra tu asistencia antes de realizar operaciones.');
    }

    /** @param  list<string>  $campos */
    private function momentoDelEvento(Request $request, array $campos): ?DateTimeImmutable
    {
        foreach ($campos as $campo) {
            $valor = $request->input($campo);

            if (! is_string($valor) || trim($valor) === '') {
                continue;
            }

            try {
                $momento = new DateTimeImmutable($valor);
            } catch (Throwable) {
                // Formato inválido: lo rechaza la validación del request; aquí se evalúa hoy.
                return null;
            }

            // Una hora del futuro, o más antigua que lo que acepta un registro offline,
            // no decide la autorización: se evalúa hoy y la validación del request la rechaza.
            $fueraDeRango = $momento > now()->toDateTimeImmutable()
                || $momento < now()->subDays(FechaEventoMovil::maxDiasAtraso())->toDateTimeImmutable();

            return $fueraDeRango ? null : $momento;
        }

        return null;
    }
}
