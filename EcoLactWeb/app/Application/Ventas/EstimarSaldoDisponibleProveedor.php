<?php

namespace App\Application\Ventas;

use App\Domain\Calidad\AnalisisCalidadRepository;
use App\Domain\Entrega\EntregaRepository;
use App\Domain\Pago\Liquidacion;
use App\Domain\Pago\LiquidacionRepository;
use App\Domain\Pago\PeriodoLiquidacion;
use App\Domain\Pago\PrecioLecheRepository;
use App\Domain\Pago\ReglaPagoCalidadRepository;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Domain\Shared\Enums\ResultadoCalidad;
use App\Domain\Ventas\VentaProductoRepository;
use DomainException;

/**
 * Estima cuánto puede descontarse todavía del pago semanal de un proveedor
 * en el periodo indicado, para validar la compra de productos (quesos) "a
 * cuenta" del pago del viernes.
 *
 * Si el periodo ya fue liquidado, el saldo se basa en el total a pagar real
 * (Liquidacion::totalPagar()). Si el periodo sigue abierto, se estima con
 * la regla semanal de calidad (ResultadoCalidad::peor): ACEPTADA al precio
 * base, OBSERVADA con el descuento a todos los litros, RECHAZADA = 0. Así no
 * se promete un saldo mayor al que realmente se pagará. Es una ESTIMACIÓN de
 * solo lectura sobre un periodo aún abierto, no la liquidación real.
 */
final class EstimarSaldoDisponibleProveedor
{
    public function __construct(
        private readonly EntregaRepository $entregas,
        private readonly AnalisisCalidadRepository $analisisCalidad,
        private readonly PrecioLecheRepository $preciosLeche,
        private readonly ReglaPagoCalidadRepository $reglasPagoCalidad,
        private readonly LiquidacionRepository $liquidaciones,
        private readonly VentaProductoRepository $ventasProducto,
    ) {}

    public function execute(int $proveedorId, PeriodoLiquidacion $periodo): float
    {
        $periodoId = $periodo->id() ?? throw new DomainException('El periodo indicado no tiene identificador válido.');

        $pagoEstimado = $this->buscarLiquidacionDelPeriodo($proveedorId, $periodoId)?->totalPagar()
            ?? $this->estimarPagoPeriodoAbierto($proveedorId, $periodo);

        $yaDescontado = 0.0;

        foreach ($this->ventasProducto->listarPorProveedorYPeriodo($proveedorId, $periodoId, 'DESCUENTO_PLANILLA') as $venta) {
            $yaDescontado += $venta->importeTotal();
        }

        return round($pagoEstimado - $yaDescontado, 2);
    }

    private function buscarLiquidacionDelPeriodo(int $proveedorId, int $periodoId): ?Liquidacion
    {
        foreach ($this->liquidaciones->listarPorProveedor($proveedorId) as $liquidacion) {
            if ($liquidacion->periodoId() === $periodoId) {
                return $liquidacion;
            }
        }

        return null;
    }

    private function estimarPagoPeriodoAbierto(int $proveedorId, PeriodoLiquidacion $periodo): float
    {
        $totalLitros = 0.0;
        $resultados = [];
        $huboEntregas = false;

        foreach ($this->entregas->listarPorProveedor($proveedorId) as $entrega) {
            if ($entrega->estado() === EstadoEntrega::ANULADA) {
                continue;
            }

            if ($entrega->fechaHora() < $periodo->fechaInicio() || $entrega->fechaHora() > $periodo->fechaFin()) {
                continue;
            }

            $huboEntregas = true;
            $resultados[] = $this->analisisCalidad->buscarPorEntregaId($entrega->id())?->resultado();
            $totalLitros += $entrega->litros();
        }

        // Misma regla semanal que SincronizarPagosProveedor: manda el PEOR resultado.
        $resultadoSemanal = ResultadoCalidad::peor($resultados);

        if (! $huboEntregas || $resultadoSemanal === ResultadoCalidad::RECHAZADA) {
            return 0.0;
        }

        $precioLeche = $this->preciosLeche->buscarVigente($periodo->fechaFin());

        if ($precioLeche === null) {
            throw new DomainException('No existe un precio base de leche vigente para estimar el saldo del proveedor.');
        }

        $ajuste = 0.0;

        if ($resultadoSemanal === ResultadoCalidad::OBSERVADA) {
            $reglaPago = $this->reglasPagoCalidad->buscarVigentePorResultado(ResultadoCalidad::OBSERVADA, $periodo->fechaFin());

            if ($reglaPago === null) {
                throw new DomainException('No existe una regla de pago por calidad vigente para estimar el saldo del proveedor.');
            }

            $ajuste = $reglaPago->calcularAjuste($precioLeche->precioLitro());
        }

        $precioLitroAplicado = max(0.0, round($precioLeche->precioLitro() + $ajuste, 4));

        return round($totalLitros * $precioLitroAplicado, 2);
    }
}
