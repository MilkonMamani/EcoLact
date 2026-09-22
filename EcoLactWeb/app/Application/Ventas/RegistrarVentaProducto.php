<?php

namespace App\Application\Ventas;

use App\Application\Shared\TransactionManager;
use App\Domain\Pago\PeriodoLiquidacion;
use App\Domain\Pago\PeriodoLiquidacionRepository;
use App\Domain\Produccion\ProductoMovimiento;
use App\Domain\Produccion\ProductoMovimientoRepository;
use App\Domain\Produccion\ProductoRepository;
use App\Domain\Produccion\ProductoStockRepository;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Ventas\CompradorRepository;
use App\Domain\Ventas\ProductoPrecioRepository;
use App\Domain\Ventas\VentaProducto;
use App\Domain\Ventas\VentaProductoRepository;
use DateTimeImmutable;
use DomainException;

/**
 * Registra la venta de un producto (ej. queso) a un proveedor, un
 * comprador mayorista o al público. Si la forma de pago es
 * DESCUENTO_PLANILLA (solo válida para PROVEEDOR), valida que el importe no
 * supere el pago semanal estimado que el proveedor tiene disponible en el
 * periodo en curso.
 */
final class RegistrarVentaProducto
{
    public function __construct(
        private readonly ProductoRepository $productos,
        private readonly ProductoPrecioRepository $precios,
        private readonly ProductoStockRepository $stock,
        private readonly ProductoMovimientoRepository $movimientos,
        private readonly ProveedorRepository $proveedores,
        private readonly CompradorRepository $compradores,
        private readonly PeriodoLiquidacionRepository $periodos,
        private readonly VentaProductoRepository $ventas,
        private readonly EstimarSaldoDisponibleProveedor $estimarSaldo,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(
        int $productoId,
        string $tipoCliente,
        float $cantidad,
        string $formaPago,
        int $registradoPorUserId,
        ?int $proveedorId = null,
        ?int $compradorId = null,
    ): VentaProducto {
        if ($this->productos->buscarPorId($productoId) === null) {
            throw new DomainException('El producto indicado no existe.');
        }

        if ($tipoCliente === 'PROVEEDOR') {
            if ($proveedorId === null || $this->proveedores->buscarPorId($proveedorId) === null) {
                throw new DomainException('El proveedor indicado no existe.');
            }
        } elseif ($compradorId !== null && $this->compradores->buscarPorId($compradorId) === null) {
            throw new DomainException('El comprador indicado no existe.');
        }

        $precioVigente = $this->precios->buscarVigente($productoId, $tipoCliente, new DateTimeImmutable);

        if ($precioVigente === null) {
            throw new DomainException('No existe un precio vigente configurado para este producto y tipo de cliente.');
        }

        $importeTotal = round($cantidad * $precioVigente->precio(), 2);
        $periodo = null;

        if ($formaPago === 'DESCUENTO_PLANILLA') {
            $periodo = $this->resolverPeriodoActual();
            $saldoDisponible = $this->estimarSaldo->execute($proveedorId, $periodo);

            if ($importeTotal > $saldoDisponible) {
                throw new DomainException('El monto del producto solicitado supera el pago estimado disponible del proveedor para este periodo.');
            }
        }

        return $this->transacciones->run(function () use (
            $productoId, $proveedorId, $compradorId, $tipoCliente, $cantidad,
            $precioVigente, $importeTotal, $formaPago, $periodo,
            $registradoPorUserId,
        ): VentaProducto {
            // Se valida el saldo DENTRO de la transacción con la fila del
            // producto bloqueada, para que dos ventas simultáneas no vendan
            // la misma existencia.
            $this->stock->bloquearProducto($productoId);

            if ($cantidad > ($this->stock->buscarPorProductoId($productoId)?->cantidadActual() ?? 0.0)) {
                throw new DomainException('No hay stock suficiente del producto solicitado.');
            }

            $venta = $this->ventas->guardar(new VentaProducto(
                id: null,
                productoId: $productoId,
                proveedorId: $proveedorId,
                compradorId: $compradorId,
                tipoCliente: $tipoCliente,
                cantidad: $cantidad,
                precioUnitario: $precioVigente->precio(),
                importeTotal: $importeTotal,
                formaPago: $formaPago,
                periodoId: $periodo?->id(),
                estado: 'REGISTRADA',
                fecha: new DateTimeImmutable,
                registradoPorUserId: $registradoPorUserId,
            ));

            $this->movimientos->guardar(new ProductoMovimiento(
                id: null,
                productoId: $productoId,
                tipo: 'VENTA',
                cantidad: $cantidad,
                loteProduccionId: null,
                ventaProductoId: $venta->id(),
                fecha: new DateTimeImmutable,
                registradoPorUserId: $registradoPorUserId,
            ));

            return $venta;
        });
    }

    /**
     * Resuelve (o crea) el periodo de liquidación EcoLact vigente hoy
     * (jueves a miércoles), igual que GenerarLiquidacion.
     */
    private function resolverPeriodoActual(): PeriodoLiquidacion
    {
        $hoy = new DateTimeImmutable;
        $diasDesdeJueves = ((int) $hoy->format('N') - 4 + 7) % 7;
        $fechaInicio = $hoy->modify("-{$diasDesdeJueves} days")->setTime(0, 0);
        $fechaFin = $fechaInicio->modify('+6 days');

        return $this->periodos->buscarPorFechas($fechaInicio, $fechaFin)
            ?? $this->periodos->guardar(new PeriodoLiquidacion(
                id: null,
                fechaInicio: $fechaInicio,
                fechaFin: $fechaFin,
                estado: 'ABIERTO',
            ));
    }
}
