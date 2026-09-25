<?php

namespace App\Http\Controllers\Api\Admin;

use App\Application\Pago\RegistrarAsistenciaQr;
use App\Application\Pago\RequiereCorreccionException;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Escaneo de QR de asistencia: el administrador autenticado escanea el QR de
 * un trabajador y el backend identifica al trabajador, valida que esté
 * activo y ya haya ingresado, y registra PRESENTE de hoy (idempotente ante
 * un segundo escaneo). Reutiliza la misma ability "pay" del resto del
 * módulo de pagos: exclusivo de ADMINISTRADOR.
 */
final class AsistenciaQrController extends Controller
{
    public function __construct(private readonly RegistrarAsistenciaQr $registrarAsistencia) {}

    public function registrar(Request $request): JsonResponse
    {
        Gate::authorize('pay', ObligacionPagoModel::class);

        $request->validate(['qr_token' => ['required', 'string']]);

        try {
            $resultado = $this->registrarAsistencia->execute(
                qrToken: (string) $request->input('qr_token'),
                adminUserId: $request->user()->id,
            );
        } catch (RequiereCorreccionException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'duplicado' => $resultado->duplicado,
            'message' => $resultado->duplicado
                ? 'La asistencia de hoy ya fue registrada.'
                : 'Asistencia registrada correctamente.',
            'jornada' => [
                'trabajador_id' => $resultado->jornada->trabajadorId(),
                'fecha' => $resultado->jornada->fecha()->format('Y-m-d'),
                'estado' => $resultado->jornada->estado()->value,
                'origen' => $resultado->jornada->origen()->value,
                'hora_marcacion' => $resultado->jornada->horaMarcacion()?->format('H:i:s'),
            ],
        ], $resultado->duplicado ? 200 : 201);
    }
}
