<?php

namespace App\Application\PersonalVentas;

use App\Domain\PersonalVentas\PersonalVentas;
use App\Domain\PersonalVentas\PersonalVentasRepository;
use DomainException;

final class ActualizarPersonalVentas
{
    public function __construct(
        private readonly PersonalVentasRepository $personal,
    ) {}

    public function execute(
        int $personalId,
        ?string $nombres = null,
        ?string $apellidos = null,
        ?string $telefono = null,
    ): PersonalVentas {
        $personal = $this->personal->buscarPorId($personalId);

        if ($personal === null) {
            throw new DomainException('El personal de ventas indicado no existe.');
        }

        return $this->personal->actualizar(new PersonalVentas(
            id: $personal->id(),
            userId: $personal->userId(),
            dni: $personal->dni(),
            nombres: $nombres ?? $personal->nombres(),
            apellidos: $apellidos ?? $personal->apellidos(),
            telefono: $telefono ?? $personal->telefono(),
            estado: $personal->estado(),
            fechaIngreso: $personal->fechaIngreso(),
        ));
    }
}
