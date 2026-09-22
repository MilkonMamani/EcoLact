<?php

namespace App\Application\Pago;

use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\ObligacionPagoRepository;
use App\Domain\Pago\PeriodoPago;

/** La única implementación de "Control del periodo": reusada por los 5 perfiles y el panel de Pagar a trabajadores. */
final class ObtenerControlPeriodoTrabajador
{
    public function __construct(private readonly ObligacionPagoRepository $obligaciones) {}

    public function execute(int $userId): ?ControlPeriodoTrabajador
    {
        $obligacion = $this->obligaciones->buscarPersonalAbiertaPorUsuario($userId)
            ?? $this->obligaciones->buscarPersonalMasRecientePorUsuario($userId);

        if ($obligacion === null) {
            return null;
        }

        $sueldoMensual = (float) ($obligacion->baseCalculo()['sueldo_base'] ?? 0.0);
        $dias = $obligacion->diasTrabajados();

        return new ControlPeriodoTrabajador(
            userId: $userId,
            obligacionId: $obligacion->id(),
            estado: $obligacion->estado()->value,
            estadoEtiqueta: $obligacion->estado()->etiqueta(),
            diasTrabajados: $dias,
            diasBase: JornadaPersonal::DIAS_BASE_PERIODO,
            diasFaltantes: max(JornadaPersonal::DIAS_BASE_PERIODO - $dias, 0),
            sueldoMensual: $sueldoMensual,
            valorDia: (float) ($obligacion->valorDia() ?? 0.0),
            acumulado: (float) ($obligacion->acumulado() ?? 0.0),
            periodoEtiqueta: PeriodoPago::etiquetaDe($obligacion->periodicidad(), $obligacion->periodoInicio()),
            periodoRango: $obligacion->periodoInicio()->format('d/m/Y').' – '.$obligacion->periodoFin()->format('d/m/Y'),
            periodoInicioIso: $obligacion->periodoInicio()->format('Y-m-d'),
            periodoFinIso: $obligacion->periodoFin()->format('Y-m-d'),
        );
    }
}
