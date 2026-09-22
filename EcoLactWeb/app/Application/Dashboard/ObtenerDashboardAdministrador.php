<?php

namespace App\Application\Dashboard;

use App\Application\Dashboard\Contracts\DashboardAdministradorQuery;

final class ObtenerDashboardAdministrador
{
    public function __construct(
        private readonly DashboardAdministradorQuery $dashboard,
    ) {}

    public function execute(string $periodo = 'hoy', ?string $desde = null, ?string $hasta = null): DashboardAdministradorData
    {
        return $this->dashboard->obtener(DashboardPeriodo::resolver($periodo, $desde, $hasta));
    }
}
