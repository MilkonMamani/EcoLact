<?php

namespace App\Domain\Proveedor;

use DateTimeImmutable;

interface ProveedorZonaHistorialRepository
{
    public function guardar(ProveedorZonaHistorial $historial): ProveedorZonaHistorial;

    public function buscarVigentePorProveedor(int $proveedorId): ?ProveedorZonaHistorial;

    public function cerrarVigente(int $proveedorId, DateTimeImmutable $fechaFin): void;

    /**
     * @return ProveedorZonaHistorial[]
     */
    public function listarPorProveedor(int $proveedorId): array;
}
