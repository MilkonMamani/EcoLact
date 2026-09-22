<?php

namespace App\Application\Liquidacion;

use App\Domain\Pago\ReglaPagoCalidad;
use App\Domain\Pago\ReglaPagoCalidadRepository;
use App\Domain\Shared\Enums\ResultadoCalidad;
use DateTimeImmutable;
use DomainException;

/**
 * Permite al ADMINISTRADOR configurar una regla de pago por calidad: el
 * ajuste (monto o porcentaje) que corresponde aplicar al precio base según
 * el resultado de calidad de la entrega.
 *
 * El tipo de ajuste y su valor vienen siempre del administrador; este Use
 * Case no hardcodea montos ni porcentajes.
 */
final class RegistrarReglaPagoCalidad
{
    public function __construct(
        private readonly ReglaPagoCalidadRepository $reglasPagoCalidad,
    ) {}

    public function execute(
        ResultadoCalidad $resultadoCalidad,
        string $tipoAjuste,
        float $valorAjuste,
        DateTimeImmutable $fechaInicio,
        ?DateTimeImmutable $fechaFin,
        string $estado,
    ): ReglaPagoCalidad {
        if ($fechaFin !== null && $fechaFin < $fechaInicio) {
            throw new DomainException('La fecha fin de la regla de pago no puede ser anterior a la fecha inicio.');
        }

        return $this->reglasPagoCalidad->guardar(new ReglaPagoCalidad(
            id: null,
            resultadoCalidad: $resultadoCalidad,
            tipoAjuste: $tipoAjuste,
            valorAjuste: $valorAjuste,
            fechaInicio: $fechaInicio,
            fechaFin: $fechaFin,
            estado: $estado,
        ));
    }
}
