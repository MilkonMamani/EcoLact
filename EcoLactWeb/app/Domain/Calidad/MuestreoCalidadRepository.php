<?php

namespace App\Domain\Calidad;

interface MuestreoCalidadRepository
{
    /** @throws MuestraYaSeleccionadaException si la entrega ya tiene muestra (índice único). */
    public function guardar(MuestreoCalidad $muestreo): MuestreoCalidad;

    public function actualizar(MuestreoCalidad $muestreo): MuestreoCalidad;

    public function buscarPorId(int $id): ?MuestreoCalidad;

    public function buscarPorEntregaId(int $entregaId): ?MuestreoCalidad;
}
