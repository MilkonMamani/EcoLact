<?php

namespace App\Domain\Proveedor;

interface ProveedorRepository
{
    public function guardar(Proveedor $proveedor): Proveedor;

    public function actualizar(Proveedor $proveedor): Proveedor;

    public function buscarPorId(int $id): ?Proveedor;

    public function buscarPorDni(string $dni): ?Proveedor;

    public function buscarPorUserId(int $userId): ?Proveedor;

    public function buscarPorQrToken(string $qrToken): ?Proveedor;

    /**
     * @return Proveedor[]
     */
    public function listarPorZona(int $zonaId): array;

    /**
     * @return Proveedor[]
     */
    public function listar(): array;
}
