<?php

namespace App\Application\PersonalVentas;

use App\Domain\PersonalVentas\PersonalVentas;
use App\Domain\PersonalVentas\PersonalVentasRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use DomainException;

final class ReactivarPersonalVentas
{
    public function __construct(
        private readonly PersonalVentasRepository $personal,
    ) {}

    public function execute(int $personalId): PersonalVentas
    {
        $personal = $this->personal->buscarPorId($personalId);

        if ($personal === null) {
            throw new DomainException('El personal de ventas indicado no existe.');
        }

        if ($personal->estado() !== EstadoUsuario::INACTIVO) {
            throw new DomainException('El personal de ventas ya se encuentra activo.');
        }

        return $this->personal->actualizar(new PersonalVentas(
            id: $personal->id(),
            userId: $personal->userId(),
            dni: $personal->dni(),
            nombres: $personal->nombres(),
            apellidos: $personal->apellidos(),
            telefono: $personal->telefono(),
            estado: EstadoUsuario::ACTIVO,
            fechaIngreso: $personal->fechaIngreso(),
        ));
    }
}
