<?php

namespace App\Domain\Ruta;

use DateTimeImmutable;

interface ProveedorRutaHistorialRepository
{
    public function guardar(ProveedorRutaHistorial $historial): ProveedorRutaHistorial;

    public function buscarVigentePorProveedor(int $proveedorId): ?ProveedorRutaHistorial;

    public function cerrarVigente(int $proveedorId, DateTimeImmutable $fechaFin): void;

    /**
     * @return ProveedorRutaHistorial[]
     */
    public function listarPorProveedor(int $proveedorId): array;
}
