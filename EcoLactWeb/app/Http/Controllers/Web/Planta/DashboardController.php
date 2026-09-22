<?php

namespace App\Http\Controllers\Web\Planta;

use App\Application\Planta\ObtenerDashboardPlanta;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ObtenerDashboardPlanta $obtenerDashboardPlanta,
    ) {}

    public function index(Request $request): View
    {
        return view('planta.dashboard', [
            'dashboard' => $this->obtenerDashboardPlanta->execute(
                userId: $request->user()->id,
                periodo: $request->query('periodo'),
                desde: $request->query('desde'),
                hasta: $request->query('hasta'),
            ),
        ]);
    }
}
