<?php

namespace App\Application\AnalistaCalidad;

use App\Domain\AnalistaCalidad\AnalistaCalidad;
use App\Domain\AnalistaCalidad\AnalistaCalidadRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use DomainException;

final class ReactivarAnalistaCalidad
{
    public function __construct(
        private readonly AnalistaCalidadRepository $analistas,
    ) {}

    public function execute(int $analistaId): AnalistaCalidad
    {
        $analista = $this->analistas->buscarPorId($analistaId);

        if ($analista === null) {
            throw new DomainException('El analista de calidad indicado no existe.');
        }

        if ($analista->estado() !== EstadoUsuario::INACTIVO) {
            throw new DomainException('El analista de calidad ya se encuentra activo.');
        }

        return $this->analistas->actualizar(new AnalistaCalidad(
            id: $analista->id(),
            userId: $analista->userId(),
            dni: $analista->dni(),
            nombres: $analista->nombres(),
            apellidos: $analista->apellidos(),
            telefono: $analista->telefono(),
            estado: EstadoUsuario::ACTIVO,
            fechaIngreso: $analista->fechaIngreso(),
        ));
    }
}
