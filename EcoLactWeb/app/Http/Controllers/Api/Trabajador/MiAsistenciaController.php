<?php

namespace App\Http\Controllers\Api\Trabajador;

use App\Application\Pago\ObtenerMiAsistencia;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Mi QR / asistencia" del trabajador autenticado (acopiador, analista de
 * calidad): su identidad de trabajador, su QR (lo escanea el ADMINISTRADOR,
 * que es quien registra la jornada) y sus jornadas del mes. Solo lectura y
 * solo de sí mismo: no recibe ningún id de trabajador del cliente ni marca
 * asistencia. Registrar la jornada (api.admin.asistencia.qr) es exclusivo del
 * administrador.
 */
final class MiAsistenciaController extends Controller
{
    public function __construct(private readonly ObtenerMiAsistencia $obtenerMiAsistencia) {}

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->obtenerMiAsistencia->execute($request->user()->id)]);
    }
}
