<?php

namespace App\Http\Controllers\Api\Proveedor;

use App\Application\Proveedor\ObtenerMiQr;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Expone el qr_token propio del proveedor autenticado. La app móvil es
 * responsable de renderizarlo como código QR (el backend solo entrega el
 * valor de texto, no genera una imagen).
 */
final class QrController extends Controller
{
    public function __construct(
        private readonly ObtenerMiQr $obtenerMiQr,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $proveedor = $this->obtenerMiQr->execute($request->user()->id);

        return response()->json([
            'data' => [
                'qr_token' => $proveedor->qrToken(),
                'nombre_completo' => $proveedor->nombreCompleto(),
                'dni' => $proveedor->dni(),
                'zona_id' => $proveedor->zonaId(),
                'estado' => $proveedor->estado()->value,
            ],
        ]);
    }
}
