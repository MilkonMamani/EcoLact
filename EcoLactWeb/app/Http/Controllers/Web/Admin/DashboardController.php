<?php

namespace App\Http\Controllers\Web\Admin;

use App\Application\Dashboard\ObtenerDashboardAdministrador;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardPeriodoRequest;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ObtenerDashboardAdministrador $obtenerDashboard,
    ) {}

    public function __invoke(DashboardPeriodoRequest $request): View
    {
        $datos = $request->validated();

        return view('admin.dashboard', [
            'dashboard' => $this->obtenerDashboard->execute(
                periodo: $datos['periodo'] ?? 'hoy',
                desde: $datos['desde'] ?? null,
                hasta: $datos['hasta'] ?? null,
            ),
        ]);
    }
}
