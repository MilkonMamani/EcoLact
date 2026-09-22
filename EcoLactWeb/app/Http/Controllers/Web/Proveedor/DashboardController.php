<?php

namespace App\Http\Controllers\Web\Proveedor;

use App\Application\Proveedor\ObtenerDashboardProveedor;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ObtenerDashboardProveedor $obtenerDashboardProveedor,
    ) {}

    public function index(Request $request): View
    {
        $proveedorId = $request->user()->proveedor?->id
            ?? throw new DomainException('No existe un proveedor asociado al usuario autenticado.');

        return view('proveedor.dashboard', [
            'dashboard' => $this->obtenerDashboardProveedor->execute(
                userId: $request->user()->id,
                proveedorId: $proveedorId,
                periodo: $request->query('periodo'),
                desde: $request->query('desde'),
                hasta: $request->query('hasta'),
            ),
        ]);
    }
}
