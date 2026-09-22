<?php

namespace App\Domain\Calidad;

interface ParametroCalidadRepository
{
    public function guardar(ParametroCalidad $parametro): ParametroCalidad;

    public function actualizar(ParametroCalidad $parametro): ParametroCalidad;

    public function buscarPorId(int $id): ?ParametroCalidad;

    public function buscarPorCodigo(string $codigo): ?ParametroCalidad;

    /**
     * @return ParametroCalidad[]
     */
    public function listar(): array;

    /**
     * @return ParametroCalidad[]
     */
    public function listarActivos(): array;
}
