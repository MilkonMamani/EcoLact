<?php

namespace App\Application\EncargadoPlanta;

use App\Domain\EncargadoPlanta\EncargadoPlanta;
use App\Domain\EncargadoPlanta\EncargadoPlantaRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use DomainException;

final class DesactivarEncargadoPlanta
{
    public function __construct(
        private readonly EncargadoPlantaRepository $encargados,
    ) {}

    public function execute(int $encargadoId): EncargadoPlanta
    {
        $encargado = $this->encargados->buscarPorId($encargadoId);

        if ($encargado === null) {
            throw new DomainException('El encargado de planta indicado no existe.');
        }

        if ($encargado->estado() !== EstadoUsuario::ACTIVO) {
            throw new DomainException('El encargado de planta ya se encuentra inactivo.');
        }

        return $this->encargados->actualizar(new EncargadoPlanta(
            id: $encargado->id(),
            userId: $encargado->userId(),
            dni: $encargado->dni(),
            nombres: $encargado->nombres(),
            apellidos: $encargado->apellidos(),
            telefono: $encargado->telefono(),
            estado: EstadoUsuario::INACTIVO,
            fechaIngreso: $encargado->fechaIngreso(),
        ));
    }
}
