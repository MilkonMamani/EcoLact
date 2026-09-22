<?php

namespace App\Application\Proveedor;

use App\Domain\Proveedor\Proveedor;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use DomainException;

final class DesactivarProveedor
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

        if ($proveedor->estado() === EstadoUsuario::INACTIVO) {
            throw new DomainException('El proveedor ya se encuentra inactivo.');
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
            estado: EstadoUsuario::INACTIVO,
            fechaRegistro: $proveedor->fechaRegistro(),
            advertenciasCount: $proveedor->advertenciasCount(),
        ));
    }
}
