<?php

namespace App\Http\Controllers\Api\Planta;

use App\Application\Pago\ObtenerMiAsistencia;
use App\Domain\EncargadoPlanta\EncargadoPlantaRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Perfil del encargado de planta autenticado y su QR de asistencia. La app lo
 * guarda en el teléfono para mostrar "Mi QR" sin conexión. Solo lectura y
 * solo de sí mismo: el QR lo escanea el ADMINISTRADOR (RegistrarAsistenciaQr);
 * aquí nunca se marca asistencia.
 */
final class PerfilController extends Controller
{
    public function __construct(
        private readonly EncargadoPlantaRepository $encargados,
        private readonly ObtenerMiAsistencia $obtenerMiAsistencia,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $encargado = $this->encargados->buscarPorUserId($user->id);
        $asistencia = $this->obtenerMiAsistencia->execute($user->id);

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'username' => $user->username ?? null,
                'nombres' => $encargado?->nombres(),
                'apellidos' => $encargado?->apellidos(),
                'dni' => $encargado?->dni(),
                'telefono' => $encargado?->telefono(),
                'rol' => 'Encargado de planta',
                'qr_token' => $asistencia['qr_token'],
                'controla_asistencia' => $asistencia['controla_asistencia'],
            ],
        ]);
    }
}
