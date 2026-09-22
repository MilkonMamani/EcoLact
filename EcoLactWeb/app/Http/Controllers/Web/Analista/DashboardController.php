<?php

namespace App\Http\Controllers\Web\Analista;

use App\Application\Calidad\ObtenerDashboardCalidad;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ObtenerDashboardCalidad $obtenerDashboardCalidad,
    ) {}

    public function index(Request $request): View
    {
        return view('calidad.dashboard', [
            'dashboard' => $this->obtenerDashboardCalidad->execute(
                userId: $request->user()->id,
                periodo: $request->query('periodo'),
                desde: $request->query('desde'),
                hasta: $request->query('hasta'),
            ),
        ]);
    }
}
