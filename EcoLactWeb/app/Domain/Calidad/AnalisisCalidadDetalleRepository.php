<?php

namespace App\Domain\Calidad;

interface AnalisisCalidadDetalleRepository
{
    /**
     * Crea o actualiza el detalle para el par (analisis, parametro).
     */
    public function guardar(AnalisisCalidadDetalle $detalle): AnalisisCalidadDetalle;

    /**
     * @return AnalisisCalidadDetalle[]
     */
    public function listarPorAnalisis(int $analisisId): array;
}
