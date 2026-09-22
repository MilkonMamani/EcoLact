<?php

namespace App\Http\Controllers\Web\Acopiador;

use App\Application\Acopiador\ObtenerDashboardAcopiador;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly ObtenerDashboardAcopiador $obtenerDashboardAcopiador,
    ) {}

    public function index(Request $request): View
    {
        $zonaId = $request->user()->acopiador?->zona_id
            ?? throw new DomainException('No existe un acopiador asociado al usuario autenticado.');

        return view('acopiador.dashboard', [
            'dashboard' => $this->obtenerDashboardAcopiador->execute(
                userId: $request->user()->id,
                zonaId: $zonaId,
                periodo: $request->query('periodo'),
                desde: $request->query('desde'),
                hasta: $request->query('hasta'),
            ),
        ]);
    }
}
