<?php

namespace App\Application\Pago;

use App\Domain\Pago\JornadaPersonal;
use App\Domain\Pago\ObligacionPago;
use App\Domain\Pago\ObligacionPagoRepository;
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
 * - Trabajador parcial (ingresó, salió o estuvo inactivo parte del mes, según
 *   sus periodos de actividad): acumulado = min(sueldo_mensual,
 *   presentes*valor_dia) — solo días realmente trabajados; los días inactivos
 *   no son falta ni día pagable.
 *
 * Presentes/faltas solo cuentan dentro de sus periodos (ContarJornadasVigentes).
 */
final class RecalcularProgresoObligacionPersonal
{
    public function __construct(
        private readonly ContarJornadasVigentes $contarJornadas,
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

        // Rol sin control de asistencia (ADMINISTRADOR): no hay jornadas ni faltas; cobra el mes
        // completo o, si ingresó/salió a mitad de mes, los días calendario en que estuvo vigente.
        if (! $this->trabajadores->controlaAsistencia($trabajador->id())) {
            $diasVigentes = $trabajador->diasVigentesEn($obligacion->periodoInicio(), $obligacion->periodoFin());
            $acumulado = $trabajador->estuvoVigenteTodoElRango($obligacion->periodoInicio(), $obligacion->periodoFin())
                ? $sueldoBase
                : min($sueldoBase, round($diasVigentes * $valorDia, 2));

            $this->obligaciones->actualizarProgresoPersonal($obligacion->id(), $diasVigentes, $valorDia, $acumulado);

            return;
        }

        ['presentes' => $presentes, 'faltas' => $faltas] = $this->contarJornadas->execute($trabajador, $obligacion->periodoInicio(), $obligacion->periodoFin());

        $acumulado = $trabajador->estuvoVigenteTodoElRango($obligacion->periodoInicio(), $obligacion->periodoFin())
            ? max(0.0, round($sueldoBase - $faltas * $valorDia, 2))
            : min($sueldoBase, round($presentes * $valorDia, 2));

        $this->obligaciones->actualizarProgresoPersonal($obligacion->id(), $presentes, $valorDia, $acumulado);
    }
}
