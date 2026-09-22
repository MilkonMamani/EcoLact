<?php

namespace App\Application\Liquidacion;

use App\Domain\Calidad\AnalisisCalidadRepository;
use App\Domain\Entrega\EntregaRepository;
use App\Domain\Pago\AjusteLiquidacionRepository;
use App\Domain\Pago\LiquidacionRepository;
use App\Domain\Pago\PagoProveedorRepository;
use App\Domain\Pago\PeriodoLiquidacionRepository;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Domain\Shared\Enums\ResultadoCalidad;
use App\Domain\Ventas\VentaProductoRepository;

/**
 * Arma el detalle completo de una liquidación (entregas del periodo,
 * incidencia de calidad, compras y ajustes del periodo, historial de
 * pagos), reconstruyendo las entregas de la semana a partir de
 * EntregaRepository (mismo criterio que EstimarSaldoDisponibleProveedor:
 * la liquidación no guarda una lista propia de "entregas del periodo",
 * solo el total agregado).
 */
final class ObtenerDetalleLiquidacion
{
    public function __construct(
        private readonly LiquidacionRepository $liquidaciones,
        private readonly PeriodoLiquidacionRepository $periodos,
        private readonly ProveedorRepository $proveedores,
        private readonly EntregaRepository $entregas,
        private readonly AnalisisCalidadRepository $analisisCalidad,
        private readonly VentaProductoRepository $ventasProducto,
        private readonly AjusteLiquidacionRepository $ajustesLiquidacion,
        private readonly PagoProveedorRepository $pagos,
        private readonly CalcularPago $calcularPago,
    ) {}

    public function execute(int $liquidacionId): ?LiquidacionDetalleData
    {
        $liquidacion = $this->liquidaciones->buscarPorId($liquidacionId);

        if ($liquidacion === null) {
            return null;
        }

        $periodo = $this->periodos->buscarPorId($liquidacion->periodoId());
        $proveedor = $this->proveedores->buscarPorId($liquidacion->proveedorId());

        $entregasFilas = [];
        $incidencia = null;

        if ($periodo !== null) {
            foreach ($this->entregas->listarPorProveedor($liquidacion->proveedorId()) as $entrega) {
                if ($entrega->estado() === EstadoEntrega::ANULADA) {
                    continue;
                }

                if ($entrega->fechaHora() < $periodo->fechaInicio() || $entrega->fechaHora() > $periodo->fechaFin()) {
                    continue;
                }

                $analisis = $this->analisisCalidad->buscarPorEntregaId($entrega->id());
                $resultado = $analisis?->resultado();

                $entregasFilas[] = [
                    'fecha' => $entrega->fechaHora()->format('d/m/Y'),
                    'litros' => $entrega->litros(),
                    'resultado' => $resultado?->value,
                    'pagables' => $resultado === ResultadoCalidad::RECHAZADA ? 0.0 : $entrega->litros(),
                ];

                if ($incidencia === null && $resultado !== null && $resultado !== ResultadoCalidad::ACEPTADA) {
                    $incidencia = [
                        'fecha' => $entrega->fechaHora()->format('d/m/Y'),
                        'resultado' => $resultado->value,
                        'entregaId' => $entrega->id(),
                    ];
                }
            }
        }

        $compras = $this->ventasProducto->listarPorProveedorYPeriodo($liquidacion->proveedorId(), $liquidacion->periodoId());
        $ajustes = $this->ajustesLiquidacion->listarPorLiquidacion($liquidacionId);
        $pagos = $this->pagos->listarPorLiquidacion($liquidacionId);

        return new LiquidacionDetalleData(
            liquidacion: $liquidacion,
            proveedorNombre: $proveedor !== null ? trim($proveedor->nombres().' '.$proveedor->apellidos()) : '—',
            proveedorDni: $proveedor?->dni() ?? '—',
            entregas: $entregasFilas,
            incidencia: $incidencia,
            compras: $compras,
            ajustes: $ajustes,
            pagos: $pagos,
            saldoPendiente: $this->calcularPago->execute($liquidacionId),
        );
    }
}
