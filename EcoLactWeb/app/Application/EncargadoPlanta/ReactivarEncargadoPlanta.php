<?php

namespace App\Application\EncargadoPlanta;

use App\Domain\EncargadoPlanta\EncargadoPlanta;
use App\Domain\EncargadoPlanta\EncargadoPlantaRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use DomainException;

final class ReactivarEncargadoPlanta
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

        if ($encargado->estado() !== EstadoUsuario::INACTIVO) {
            throw new DomainException('El encargado de planta ya se encuentra activo.');
        }

        return $this->encargados->actualizar(new EncargadoPlanta(
            id: $encargado->id(),
            userId: $encargado->userId(),
            dni: $encargado->dni(),
            nombres: $encargado->nombres(),
            apellidos: $encargado->apellidos(),
            telefono: $encargado->telefono(),
            estado: EstadoUsuario::ACTIVO,
            fechaIngreso: $encargado->fechaIngreso(),
        ));
    }
}
