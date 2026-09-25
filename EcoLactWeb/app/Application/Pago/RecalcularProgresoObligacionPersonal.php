<?php

namespace App\Application\Pago;

use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\JornadaPersonalRepository;
use App\Domain\Pago\ObligacionPago;
use App\Domain\Pago\ObligacionPagoRepository;
use App\Domain\Shared\Enums\EstadoJornada;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Domain\Trabajador\TrabajadorRepository;

/**
 * Recalcula días trabajados / valor por día / acumulado de una obligación de
 * personal EN_CURSO a partir de las jornadas realmente registradas (nunca de
 * un contador incremental), para que el orden en que llegan las jornadas o
 * las visitas a la pantalla nunca la desincronicen. Nunca cambia el estado
 * (el cierre EN_CURSO->PENDIENTE es exclusivo de CerrarNominaMensual) ni
 * toca proveedores ni obligaciones que ya no estén EN_CURSO.
 *
 * Fórmula (mes calendario, valor_dia = sueldo_mensual / 30 siempre):
 * - Trabajador activo TODO el mes (ingresó antes o el día 1 y sigue activo
 *   al cierre): acumulado = sueldo_mensual - faltas*valor_dia (nunca menos
 *   por tener el mes 28/29/31 días).
 * - Trabajador parcial (ingresó o salió durante el mes): acumulado =
 *   min(sueldo_mensual, presentes*valor_dia) — nunca más que el sueldo
 *   mensual aunque el mes tenga 31 días.
 */
final class RecalcularProgresoObligacionPersonal
{
    public function __construct(
        private readonly JornadaPersonalRepository $jornadas,
        private readonly ObligacionPagoRepository $obligaciones,
        private readonly TrabajadorRepository $trabajadores,
    ) {}

    public function execute(ObligacionPago $obligacion): void
    {
        if ($obligacion->tipoBeneficiario() !== TipoBeneficiarioPago::PERSONAL || ! $obligacion->estaEnCurso()) {
            return;
        }

        $trabajador = $this->trabajadores->buscarPorUserId($obligacion->userId());

        if ($trabajador === null) {
            return;
        }

        $sueldoBase = (float) ($obligacion->baseCalculo()['sueldo_base'] ?? 0.0);
        $valorDia = round($sueldoBase / JornadaPersonal::DIAS_BASE_PERIODO, 2);

        $presentes = $this->jornadas->contarEnPeriodo($trabajador->id(), $obligacion->periodoInicio(), $obligacion->periodoFin(), EstadoJornada::PRESENTE);
        $faltas = $this->jornadas->contarEnPeriodo($trabajador->id(), $obligacion->periodoInicio(), $obligacion->periodoFin(), EstadoJornada::FALTA);

        $acumulado = $trabajador->estuvoVigenteTodoElRango($obligacion->periodoInicio(), $obligacion->periodoFin())
            ? max(0.0, round($sueldoBase - $faltas * $valorDia, 2))
            : min($sueldoBase, round($presentes * $valorDia, 2));

        $this->obligaciones->actualizarProgresoPersonal($obligacion->id(), $presentes, $valorDia, $acumulado);
    }
}
