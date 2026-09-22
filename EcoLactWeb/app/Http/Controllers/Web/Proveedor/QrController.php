<?php

namespace App\Http\Controllers\Web\Proveedor;

use App\Application\Proveedor\ObtenerMiQr;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Expone el qr_token propio del proveedor autenticado (Etapa 10.2.1,
 * sección 9). NO genera una imagen QR gráfica todavía (fuera de alcance:
 * requeriría una librería externa); solo prepara los datos (proveedor +
 * qr_token) para la futura vista Blade.
 */
final class QrController extends Controller
{
    public function __construct(
        private readonly ObtenerMiQr $obtenerMiQr,
    ) {}

    /**
     * Vista futura (Etapa 10.3): proveedor.qr
     */
    public function show(Request $request): View
    {
        $proveedor = $this->obtenerMiQr->execute($request->user()->id);

        return view('proveedor.qr', [
            'proveedor' => $proveedor,
            'zona' => ZonaModel::find($proveedor->zonaId()),
        ]);
    }
}
