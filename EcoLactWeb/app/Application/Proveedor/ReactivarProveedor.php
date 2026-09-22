<?php

namespace App\Application\Proveedor;

use App\Domain\Proveedor\Proveedor;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use DomainException;

/**
 * Reactiva (ACTIVO) a un proveedor que está INACTIVO y reinicia su contador
 * de advertencias de calidad: la reactivación es una decisión explícita del
 * ADMINISTRADOR de "empezar de nuevo" con ese proveedor.
 */
final class ReactivarProveedor
{
    public function __construct(
        private readonly ProveedorRepository $proveedores,
    ) {}

    public function execute(int $proveedorId): Proveedor
    {
        $proveedor = $this->proveedores->buscarPorId($proveedorId);

        if ($proveedor === null) {
            throw new DomainException('El proveedor indicado no existe.');
        }

        if ($proveedor->estado() !== EstadoUsuario::INACTIVO) {
            throw new DomainException('El proveedor ya se encuentra activo.');
        }

        return $this->proveedores->actualizar(new Proveedor(
            id: $proveedor->id(),
            userId: $proveedor->userId(),
            zonaId: $proveedor->zonaId(),
            dni: $proveedor->dni(),
            nombres: $proveedor->nombres(),
            apellidos: $proveedor->apellidos(),
            telefono: $proveedor->telefono(),
            qrToken: $proveedor->qrToken(),
            estado: EstadoUsuario::ACTIVO,
            fechaRegistro: $proveedor->fechaRegistro(),
            advertenciasCount: 0,
        ));
    }
}
