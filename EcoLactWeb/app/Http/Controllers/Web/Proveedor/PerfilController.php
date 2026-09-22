<?php

namespace App\Http\Controllers\Web\Proveedor;

use App\Application\Proveedor\ObtenerProveedor;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Autoservicio del PROVEEDOR: siempre resuelve su propio registro a partir
 * de $request->user() (nunca un proveedor_id de la URL, ver Etapa 10.2.1
 * sección 11). No necesita Policy: no hay ningún proveedor_id externo que
 * pudiera intentar suplantarse (mismo criterio ya documentado en
 * NotificacionController::index, Etapa 10.1.1).
 */
final class PerfilController extends Controller
{
    public function __construct(
        private readonly ObtenerProveedor $obtenerProveedor,
    ) {}

    /**
     * Vista futura (Etapa 10.3): proveedor.perfil
     */
    public function show(Request $request): View
    {
        $proveedorId = $request->user()->proveedor?->id
            ?? throw new DomainException('No existe un proveedor asociado al usuario autenticado.');

        return view('proveedor.perfil', [
            'proveedor' => $this->obtenerProveedor->execute($proveedorId),
        ]);
    }
}
