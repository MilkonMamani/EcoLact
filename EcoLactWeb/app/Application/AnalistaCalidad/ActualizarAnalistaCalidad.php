<?php

namespace App\Application\AnalistaCalidad;

use App\Domain\AnalistaCalidad\AnalistaCalidad;
use App\Domain\AnalistaCalidad\AnalistaCalidadRepository;
use DomainException;

final class ActualizarAnalistaCalidad
{
    public function __construct(
        private readonly AnalistaCalidadRepository $analistas,
    ) {}

    public function execute(
        int $analistaId,
        ?string $nombres = null,
        ?string $apellidos = null,
        ?string $telefono = null,
    ): AnalistaCalidad {
        $analista = $this->analistas->buscarPorId($analistaId);

        if ($analista === null) {
            throw new DomainException('El analista de calidad indicado no existe.');
        }

        return $this->analistas->actualizar(new AnalistaCalidad(
            id: $analista->id(),
            userId: $analista->userId(),
            dni: $analista->dni(),
            nombres: $nombres ?? $analista->nombres(),
            apellidos: $apellidos ?? $analista->apellidos(),
            telefono: $telefono ?? $analista->telefono(),
            estado: $analista->estado(),
            fechaIngreso: $analista->fechaIngreso(),
        ));
    }
}
