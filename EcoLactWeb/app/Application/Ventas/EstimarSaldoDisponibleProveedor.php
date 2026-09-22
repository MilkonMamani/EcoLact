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
 * el mismo criterio que usará GenerarLiquidacion al cerrarlo (litros
 * pagables del periodo × el precio único que corresponde según si hubo o
 * no incidencias de calidad), para no prometer un saldo mayor al que
 * realmente se pagará. Esta duplicación puntual del cálculo de
 * GenerarLiquidacion es intencional: aquí es una ESTIMACIÓN de solo
 * lectura sobre un periodo aún abierto, no la liquidación real.
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
        $totalLitrosPagables = 0.0;
        $tuvoIncidencia = false;
        $huboEntregas = false;

        foreach ($this->entregas->listarPorProveedor($proveedorId) as $entrega) {
            if ($entrega->estado() === EstadoEntrega::ANULADA) {
                continue;
            }

            if ($entrega->fechaHora() < $periodo->fechaInicio() || $entrega->fechaHora() > $periodo->fechaFin()) {
                continue;
            }

            $huboEntregas = true;
            $analisis = $this->analisisCalidad->buscarPorEntregaId($entrega->id());
            $resultado = $analisis?->resultado();

            if ($resultado === ResultadoCalidad::RECHAZADA) {
                $tuvoIncidencia = true;

                continue;
            }

            if ($resultado === ResultadoCalidad::OBSERVADA) {
                $tuvoIncidencia = true;
            }

            $totalLitrosPagables += $entrega->litros();
        }

        if (! $huboEntregas) {
            return 0.0;
        }

        $precioLeche = $this->preciosLeche->buscarVigente($periodo->fechaFin());

        if ($precioLeche === null) {
            throw new DomainException('No existe un precio base de leche vigente para estimar el saldo del proveedor.');
        }

        $resultadoAplicable = $tuvoIncidencia ? ResultadoCalidad::OBSERVADA : ResultadoCalidad::ACEPTADA;
        $reglaPago = $this->reglasPagoCalidad->buscarVigentePorResultado($resultadoAplicable, $periodo->fechaFin());

        if ($reglaPago === null) {
            throw new DomainException('No existe una regla de pago por calidad vigente para estimar el saldo del proveedor.');
        }

        $precioLitroAplicado = max(0.0, round($precioLeche->precioLitro() + $reglaPago->calcularAjuste($precioLeche->precioLitro()), 4));

        return round($totalLitrosPagables * $precioLitroAplicado, 2);
    }
}
