<?php

namespace App\Http\Controllers\Api\Trabajador;

use App\Application\Pago\EstadoAsistenciaActual;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/me/asistencia: ¿el usuario autenticado puede operar hoy? Lo usa
 * la app para habilitar sus acciones y guardar la autorización del día (con
 * su fecha) para trabajar sin conexión. Solo lectura; la fuente de verdad es
 * EstadoAsistenciaActual y el servidor vuelve a validar cada operación.
 */
final class EstadoAsistenciaController extends Controller
{
    public function __construct(private readonly EstadoAsistenciaActual $estadoAsistencia) {}

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->estadoAsistencia->paraUsuario($request->user()->id)->toArray()]);
    }
}
