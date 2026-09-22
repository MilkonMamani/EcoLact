<?php

namespace App\Application\Liquidacion;

use App\Application\Shared\CampoActualizable;
use App\Domain\Pago\ReglaPagoCalidad;
use App\Domain\Pago\ReglaPagoCalidadRepository;
use App\Domain\Shared\Enums\ResultadoCalidad;
use DateTimeImmutable;
use DomainException;

/**
 * Actualiza una regla de pago por calidad existente. Solo modifica los
 * campos que el administrador envía; el resto conserva su valor actual.
 * Los montos/porcentajes usados siguen siendo los que recibe, nunca
 * valores hardcodeados.
 *
 * fecha_fin es nullable: "no enviarla" y "enviarla explícitamente como
 * null" son cosas distintas, por lo que se recibe envuelta en
 * CampoActualizable (ver ActualizarReglaCalidad para el detalle del patrón).
 *
 * @param  CampoActualizable<DateTimeImmutable|null>|null  $fechaFin
 */
final class ActualizarReglaPagoCalidad
{
    public function __construct(
        private readonly ReglaPagoCalidadRepository $reglasPagoCalidad,
    ) {}

    public function execute(
        int $reglaId,
        ?ResultadoCalidad $resultadoCalidad = null,
        ?string $tipoAjuste = null,
        ?float $valorAjuste = null,
        ?DateTimeImmutable $fechaInicio = null,
        ?CampoActualizable $fechaFin = null,
        ?string $estado = null,
    ): ReglaPagoCalidad {
        $regla = $this->reglasPagoCalidad->buscarPorId($reglaId);

        if ($regla === null) {
            throw new DomainException('La regla de pago por calidad indicada no existe.');
        }

        $nuevaFechaInicio = $fechaInicio ?? $regla->fechaInicio();
        $nuevaFechaFin = CampoActualizable::resolver($fechaFin, $regla->fechaFin());

        if ($nuevaFechaFin !== null && $nuevaFechaFin < $nuevaFechaInicio) {
            throw new DomainException('La fecha fin de la regla de pago no puede ser anterior a la fecha inicio.');
        }

        return $this->reglasPagoCalidad->actualizar(new ReglaPagoCalidad(
            id: $regla->id(),
            resultadoCalidad: $resultadoCalidad ?? $regla->resultadoCalidad(),
            tipoAjuste: $tipoAjuste ?? $regla->tipoAjuste(),
            valorAjuste: $valorAjuste ?? $regla->valorAjuste(),
            fechaInicio: $nuevaFechaInicio,
            fechaFin: $nuevaFechaFin,
            estado: $estado ?? $regla->estado(),
        ));
    }
}
