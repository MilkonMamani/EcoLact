<?php

namespace App\Domain\Produccion;

interface PlanificacionProduccionRepository
{
    public function guardar(PlanificacionProduccion $planificacion): PlanificacionProduccion;

    public function actualizar(PlanificacionProduccion $planificacion): PlanificacionProduccion;

    public function buscarPorId(int $id): ?PlanificacionProduccion;
}
