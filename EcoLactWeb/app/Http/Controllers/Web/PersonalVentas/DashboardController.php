<?php

namespace App\Http\Controllers\Web\PersonalVentas;

use App\Application\PersonalVentas\ObtenerDashboardVentas;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ObtenerDashboardVentas $obtenerDashboardVentas,
    ) {}

    public function index(Request $request): View
    {
        return view('ventas-panel.dashboard', [
            'dashboard' => $this->obtenerDashboardVentas->execute(
                userId: $request->user()->id,
                periodo: $request->query('periodo'),
                desde: $request->query('desde'),
                hasta: $request->query('hasta'),
            ),
        ]);
    }
}
