<?php

namespace App\Application\Pago;

use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\JornadaPersonalRepository;
use App\Domain\Pago\ObligacionPago;
use App\Domain\Pago\ObligacionPagoRepository;
use App\Domain\Shared\Enums\EstadoObligacionPago;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;

/**
 * Recalcula días trabajados / valor por día / acumulado / estado de una
 * obligación de personal a partir de las jornadas realmente registradas
 * (nunca de un contador incremental), para que el orden en que llegan las
 * jornadas o las visitas a la pantalla nunca la desincronicen. No hace nada
 * sobre proveedores ni sobre obligaciones ya PAGADAS.
 */
final class RecalcularProgresoObligacionPersonal
{
    public function __construct(
        private readonly JornadaPersonalRepository $jornadas,
        private readonly ObligacionPagoRepository $obligaciones,
    ) {}

    public function execute(ObligacionPago $obligacion): void
    {
        if ($obligacion->tipoBeneficiario() !== TipoBeneficiarioPago::PERSONAL || $obligacion->estaPagada()) {
            return;
        }

        $dias = $this->jornadas->contarEnPeriodo($obligacion->userId(), $obligacion->periodoInicio(), $obligacion->periodoFin());
        $sueldoBase = (float) ($obligacion->baseCalculo()['sueldo_base'] ?? 0.0);
        $valorDia = round($sueldoBase / JornadaPersonal::DIAS_BASE_PERIODO, 2);
        $acumulado = round($dias * $valorDia, 2);
        $estado = $dias >= JornadaPersonal::DIAS_BASE_PERIODO ? EstadoObligacionPago::PENDIENTE : EstadoObligacionPago::EN_CURSO;

        $this->obligaciones->actualizarProgresoPersonal($obligacion->id(), $dias, $valorDia, $acumulado, $estado);
    }
}
