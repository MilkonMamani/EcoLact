<?php

namespace App\Application\Dashboard;

use App\Application\Pago\CalcularDevengoPersonal;
use App\Application\Pago\Contracts\DevengoProveedoresQuery;
use App\Application\Pago\Contracts\SaldosObligacionesQuery;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;

/**
 * Fuente única de los importes financieros del dashboard. Solo lee: abrir el
 * dashboard nunca crea obligaciones, cierra semanas ni registra pagos.
 *
 * Sin doble conteo en el saldo de trabajadores: un mes ya cerrado aporta su
 * obligación (monto − pagos) y el mes en curso aporta solo su devengo a la
 * fecha (su obligación EN_CURSO es una proyección y no se suma).
 */
final class ObtenerResumenFinanciero
{
    public function __construct(
        private readonly DevengoProveedoresQuery $proveedores,
        private readonly SaldosObligacionesQuery $saldos,
        private readonly CalcularDevengoPersonal $devengoPersonal,
    ) {}

    public function execute(DashboardPeriodo $periodo): ResumenFinanciero
    {
        $desde = $periodo->desde->toDateTimeImmutable();
        $hasta = $periodo->hasta->startOfDay()->toDateTimeImmutable();
        $corte = $periodo->fechaCorte()->toDateTimeImmutable();

        $acopio = $this->proveedores->resumen($desde, $periodo->finExclusivo()->toDateTimeImmutable());
        $saldoProveedores = $this->saldos->saldoAl(TipoBeneficiarioPago::PROVEEDOR, $corte);
        $programadoProveedores = $this->saldos->programadoEntre(TipoBeneficiarioPago::PROVEEDOR, $desde, $hasta, $corte);

        $personal = $this->devengoPersonal->execute($desde, $hasta, $corte);
        $saldoPersonal = $this->saldos->saldoAl(TipoBeneficiarioPago::PERSONAL, $corte);
        $programadoPersonal = $this->saldos->programadoEntre(TipoBeneficiarioPago::PERSONAL, $desde, $hasta, $corte);
        $programadoPersonal['importe'] = round($programadoPersonal['importe'] + $personal->programadoEnCurso, 2);
        $programadoPersonal['porPagar'] = round($programadoPersonal['porPagar'] + $personal->programadoEnCurso, 2);
        $programadoPersonal['estimado'] = $personal->programadoEnCurso;

        $proveedores = [
            'devengado' => $acopio['devengado'],
            'sujetoACalidad' => $acopio['sujetoACalidad'],
            'entregas' => $acopio['entregas'],
            'litros' => [
                'recibidos' => $acopio['litrosRecibidos'],
                'enAnalisis' => $acopio['litrosEnAnalisis'],
                'valorizados' => $acopio['litrosValorizados'],
                'noPagables' => $acopio['litrosNoPagables'],
                'sinValorizar' => $acopio['litrosSinValorizar'],
            ],
            'pendiente' => $saldoProveedores['pendiente'],
            'pendienteObligaciones' => $saldoProveedores['obligaciones'],
            'vencido' => $saldoProveedores['vencido'],
            'vencidas' => $saldoProveedores['vencidas'],
            'programado' => $programadoProveedores,
            'semana' => $periodo->semanaProveedorUnica(),
        ];

        $trabajadores = [
            'devengado' => $personal->total,
            'dias' => $personal->dias,
            'porRol' => $personal->porRol,
            'porTrabajador' => $personal->porTrabajador,
            'pendiente' => round($saldoPersonal['pendiente'] + $personal->enCursoAlCorte, 2),
            'pendienteCerrado' => $saldoPersonal['pendiente'],
            'pendienteEnCurso' => $personal->enCursoAlCorte,
            'pendienteObligaciones' => $saldoPersonal['obligaciones'],
            'vencido' => $saldoPersonal['vencido'],
            'vencidas' => $saldoPersonal['vencidas'],
            'programado' => $programadoPersonal,
            'avisos' => $personal->avisos,
            'incidencias' => $personal->incidencias,
            'mes' => $periodo->mesTrabajadoUnico(),
        ];

        return new ResumenFinanciero(
            corte: $corte->format('Y-m-d'),
            proveedores: $proveedores,
            trabajadores: $trabajadores,
            totales: [
                'devengado' => round($proveedores['devengado'] + $trabajadores['devengado'], 2),
                'pendiente' => round($proveedores['pendiente'] + $trabajadores['pendiente'], 2),
                'programado' => round($programadoProveedores['importe'] + $programadoPersonal['importe'], 2),
                'programadoPorPagar' => round($programadoProveedores['porPagar'] + $programadoPersonal['porPagar'], 2),
                // Solo obligaciones reconocidas: sin el devengo provisional del mes en curso.
                'pendienteReconocido' => round($proveedores['pendiente'] + $trabajadores['pendienteCerrado'], 2),
                'venceEnPeriodo' => round($programadoProveedores['porPagar'] + $programadoPersonal['porPagar'] - $personal->programadoEnCurso, 2),
            ],
        );
    }
}
