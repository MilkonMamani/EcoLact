<?php

namespace App\Application\Calidad;

use App\Domain\Calidad\ParametroCalidadRepository;
use App\Domain\Calidad\ReglaCalidad;
use App\Domain\Calidad\ReglaCalidadRepository;
use App\Domain\Shared\Enums\ResultadoCalidad;
use DateTimeImmutable;
use DomainException;

/**
 * Permite al ADMINISTRADOR configurar una regla de calidad: parámetro,
 * rango válido, resultado en caso de incumplimiento, prioridad y vigencia.
 *
 * Este Use Case NO decide ni inventa límites oficiales (por ejemplo, "pH
 * mínimo = 6.6"): únicamente guarda los valores que el administrador
 * proporciona.
 */
final class RegistrarReglaCalidad
{
    public function __construct(
        private readonly ReglaCalidadRepository $reglasCalidad,
        private readonly ParametroCalidadRepository $parametros,
    ) {}

    public function execute(
        int $parametroId,
        ?float $valorMinimo,
        ?float $valorMaximo,
        ResultadoCalidad $resultadoIncumplimiento,
        int $prioridad,
        DateTimeImmutable $fechaInicio,
        ?DateTimeImmutable $fechaFin,
        string $estado,
    ): ReglaCalidad {
        if ($this->parametros->buscarPorId($parametroId) === null) {
            throw new DomainException('El parámetro de calidad indicado no existe.');
        }

        if ($fechaFin !== null && $fechaFin < $fechaInicio) {
            throw new DomainException('La fecha fin de la regla no puede ser anterior a la fecha inicio.');
        }

        return $this->reglasCalidad->guardar(new ReglaCalidad(
            id: null,
            parametroId: $parametroId,
            valorMinimo: $valorMinimo,
            valorMaximo: $valorMaximo,
            resultadoIncumplimiento: $resultadoIncumplimiento,
            prioridad: $prioridad,
            fechaInicio: $fechaInicio,
            fechaFin: $fechaFin,
            estado: $estado,
        ));
    }
}
