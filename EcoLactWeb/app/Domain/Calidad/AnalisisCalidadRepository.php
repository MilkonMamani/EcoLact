<?php

namespace App\Domain\Calidad;

interface AnalisisCalidadRepository
{
    public function guardar(AnalisisCalidad $analisis): AnalisisCalidad;

    public function actualizar(AnalisisCalidad $analisis): AnalisisCalidad;

    public function buscarPorId(int $id): ?AnalisisCalidad;

    public function buscarPorEntregaId(int $entregaId): ?AnalisisCalidad;

    /**
     * @return AnalisisCalidad[]
     */
    public function listar(): array;

    /**
     * @return AnalisisCalidad[]
     */
    public function listarPorProveedorId(int $proveedorId): array;
}
