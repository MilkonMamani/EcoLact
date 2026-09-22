<?php

namespace App\Http\Controllers\Web;

use App\Application\Pago\ObtenerControlPeriodoTrabajador;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Perfil del administrador autenticado ("Mi perfil"). A diferencia de los
 * otros roles del personal, ADMINISTRADOR no tiene ficha ni pantalla de
 * listado/detalle por id -no se pidió CRUD-, así que esta es una autovista:
 * siempre muestra al usuario que inició sesión, solo para alojar el Control
 * del periodo con la misma lógica reusable que los demás roles.
 */
final class AdministradorController extends Controller
{
    public function __construct(private readonly ObtenerControlPeriodoTrabajador $obtenerControlPeriodo) {}

    public function show(Request $request): View
    {
        $administrador = $request->user();

        return view('admin.administrador.show', [
            'administrador' => $administrador,
            'controlPeriodo' => $this->obtenerControlPeriodo->execute($administrador->id),
        ]);
    }
}
