<?php

namespace App\Application\Dashboard\Contracts;

use App\Application\Dashboard\DashboardAdministradorData;
use App\Application\Dashboard\DashboardPeriodo;

interface DashboardAdministradorQuery
{
    public function obtener(DashboardPeriodo $periodo): DashboardAdministradorData;
}
