<?php

namespace App\Application\Ventas;

use App\Domain\Pago\PeriodoLiquidacion;
use App\Domain\Pago\PeriodoLiquidacionRepository;
use DateTimeImmutable;

/**
 * El periodo de liquidación EcoLact vigente hoy (jueves a miércoles), igual
 * que GenerarLiquidacion: a él se cargan las compras en DESCUENTO_PLANILLA.
 */
final class PeriodoDescuentoActual
{
    public function __construct(
        private readonly PeriodoLiquidacionRepository $periodos,
    ) {}

    /**
     * Solo lectura: el periodo guardado o, si aún no existe, uno sin id con
     * las mismas fechas (para estimar el saldo sin escribir nada).
     */
    public function consultar(): PeriodoLiquidacion
    {
        [$fechaInicio, $fechaFin] = $this->fechas();

        return $this->periodos->buscarPorFechas($fechaInicio, $fechaFin)
            ?? new PeriodoLiquidacion(id: null, fechaInicio: $fechaInicio, fechaFin: $fechaFin, estado: 'ABIERTO');
    }

    /** Para registrar la venta: el periodo guardado, creándolo si hace falta. */
    public function obtenerOCrear(): PeriodoLiquidacion
    {
        $periodo = $this->consultar();

        return $periodo->id() !== null ? $periodo : $this->periodos->guardar($periodo);
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function fechas(): array
    {
        $hoy = new DateTimeImmutable;
        $diasDesdeJueves = ((int) $hoy->format('N') - 4 + 7) % 7;
        $fechaInicio = $hoy->modify("-{$diasDesdeJueves} days")->setTime(0, 0);

        return [$fechaInicio, $fechaInicio->modify('+6 days')];
    }
}
