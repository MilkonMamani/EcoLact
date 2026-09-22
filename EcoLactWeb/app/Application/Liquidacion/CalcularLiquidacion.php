<?php

namespace App\Application\Liquidacion;

use App\Domain\Calidad\AnalisisCalidadRepository;
use App\Domain\Entrega\EntregaRepository;
use App\Domain\Pago\PrecioLecheRepository;
use App\Domain\Pago\ReglaPagoCalidadRepository;
use App\Domain\Pago\ValorizacionEntregaRepository;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Domain\Shared\Enums\ResultadoCalidad;
use App\Domain\Ventas\VentaProductoRepository;
use DateTimeImmutable;
use DomainException;

/**
 * Calculo compartido por la vista previa y el registro, sin escrituras.
 *
 * Con $aplicaCalidad = false (configuración de pago del proveedor sin
 * ajustes de calidad) todo el periodo se paga al precio base de leche, sin
 * consultar reglas_pago_calidad; la leche RECHAZADA sigue sin pagarse porque
 * nunca ingresó a la planta (ya viene con litros pagables = 0).
 */
final class CalcularLiquidacion
{
    public function __construct(
        private readonly EntregaRepository $entregas,
        private readonly AnalisisCalidadRepository $analisisCalidad,
        private readonly ValorizacionEntregaRepository $valorizaciones,
        private readonly PrecioLecheRepository $preciosLeche,
        private readonly ReglaPagoCalidadRepository $reglasPagoCalidad,
        private readonly VentaProductoRepository $ventasProducto,
    ) {}

    public function execute(int $proveedorId, DateTimeImmutable $fechaInicio, DateTimeImmutable $fechaFin, ?int $periodoId = null, array $ajustes = [], bool $aplicaCalidad = true): array
    {
        $fechaInicio = $fechaInicio->setTime(0, 0);
        $fechaFin = $fechaFin->setTime(23, 59, 59);
        $totalLitrosEntregados = 0.0;
        $totalLitrosPagables = 0.0;
        $tuvoIncidencia = false;
        $detallesAPersistir = [];

        foreach ($this->entregas->listarPorProveedor($proveedorId) as $entrega) {
            if ($entrega->estado() === EstadoEntrega::ANULADA) {
                continue;
            }

            if ($entrega->fechaHora() < $fechaInicio || $entrega->fechaHora() > $fechaFin) {
                continue;
            }

            $totalLitrosEntregados += $entrega->litros();

            $valorizacion = $this->valorizaciones->buscarPorEntregaId($entrega->id());

            if ($valorizacion === null) {
                continue;
            }

            $totalLitrosPagables += $valorizacion->litrosPagables();
            $detallesAPersistir[] = $valorizacion;

            $analisis = $this->analisisCalidad->buscarPorEntregaId($entrega->id());

            if ($analisis !== null && $analisis->resultado() !== null && $analisis->resultado() !== ResultadoCalidad::ACEPTADA) {
                $tuvoIncidencia = true;
            }
        }

        if ($detallesAPersistir === []) {
            throw new DomainException('No hay entregas valorizadas para liquidar en este periodo.');
        }

        $precioLeche = $this->preciosLeche->buscarVigente($fechaFin);

        if ($precioLeche === null) {
            throw new DomainException('No existe un precio base de leche vigente para el periodo indicado.');
        }

        if ($aplicaCalidad) {
            $resultadoAplicable = $tuvoIncidencia ? ResultadoCalidad::OBSERVADA : ResultadoCalidad::ACEPTADA;
            $reglaPago = $this->reglasPagoCalidad->buscarVigentePorResultado($resultadoAplicable, $fechaFin);

            if ($reglaPago === null) {
                throw new DomainException('No existe una regla de pago por calidad vigente para el periodo indicado.');
            }

            $precioLitroAplicado = max(0.0, round($precioLeche->precioLitro() + $reglaPago->calcularAjuste($precioLeche->precioLitro()), 4));
        } else {
            $precioLitroAplicado = round($precioLeche->precioLitro(), 4);
        }

        $subtotal = round($totalLitrosPagables * $precioLitroAplicado, 2);

        // Las compras de producto (ej. queso) que el proveedor pidió que se
        // le descontaran del pago de este periodo se aplican aquí como
        // ajustes negativos, junto con los ajustes manuales recibidos
        // (ej. sanciones).
        foreach ($periodoId !== null ? $this->ventasProducto->listarPorProveedorYPeriodo($proveedorId, $periodoId, 'DESCUENTO_PLANILLA') : [] as $venta) {
            $ajustes[] = [
                'tipo' => 'DESCUENTO_PRODUCTO',
                'descripcion' => "Compra de producto #{$venta->productoId()} a cuenta del pago (venta #{$venta->id()}).",
                'monto' => -$venta->importeTotal(),
                'ventaProductoId' => $venta->id(),
            ];
        }

        $totalAjustes = round(array_sum(array_column($ajustes, 'monto')), 2);
        $totalPagar = round($subtotal + $totalAjustes, 2);

        return compact('totalLitrosEntregados', 'totalLitrosPagables', 'precioLitroAplicado', 'subtotal', 'totalAjustes', 'totalPagar', 'tuvoIncidencia', 'detallesAPersistir', 'ajustes') + [
            'importeBase' => round($totalLitrosEntregados * $precioLeche->precioLitro(), 2),
            'descuentoCalidad' => max(0.0, round($totalLitrosEntregados * $precioLeche->precioLitro() - $subtotal, 2)),
        ];
    }
}
