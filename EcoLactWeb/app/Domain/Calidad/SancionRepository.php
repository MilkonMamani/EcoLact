<?php

namespace App\Domain\Calidad;

interface SancionRepository
{
    public function guardar(Sancion $sancion): Sancion;

    public function buscarPorId(int $id): ?Sancion;

    /**
     * @return Sancion[]
     */
    public function listarPorProveedor(int $proveedorId): array;
}
