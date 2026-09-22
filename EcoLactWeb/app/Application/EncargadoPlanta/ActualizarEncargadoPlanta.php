<?php

namespace App\Application\EncargadoPlanta;

use App\Domain\EncargadoPlanta\EncargadoPlanta;
use App\Domain\EncargadoPlanta\EncargadoPlantaRepository;
use DomainException;

/**
 * Actualiza los datos normales de un encargado de planta (nombres,
 * apellidos, teléfono). NO admite cambiar dni, username ni estado (ver
 * Desactivar/ReactivarEncargadoPlanta).
 */
final class ActualizarEncargadoPlanta
{
    public function __construct(
        private readonly EncargadoPlantaRepository $encargados,
    ) {}

    public function execute(
        int $encargadoId,
        ?string $nombres = null,
        ?string $apellidos = null,
        ?string $telefono = null,
    ): EncargadoPlanta {
        $encargado = $this->encargados->buscarPorId($encargadoId);

        if ($encargado === null) {
            throw new DomainException('El encargado de planta indicado no existe.');
        }

        return $this->encargados->actualizar(new EncargadoPlanta(
            id: $encargado->id(),
            userId: $encargado->userId(),
            dni: $encargado->dni(),
            nombres: $nombres ?? $encargado->nombres(),
            apellidos: $apellidos ?? $encargado->apellidos(),
            telefono: $telefono ?? $encargado->telefono(),
            estado: $encargado->estado(),
            fechaIngreso: $encargado->fechaIngreso(),
        ));
    }
}
