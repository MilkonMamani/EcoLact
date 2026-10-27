<?php

namespace App\Application\Ventas;

use App\Application\Caja\RegistrarMovimientoCaja;
use App\Application\Shared\TransactionManager;
use App\Domain\Produccion\ProductoMovimiento;
use App\Domain\Produccion\ProductoMovimientoRepository;
use App\Domain\Produccion\ProductoRepository;
use App\Domain\Produccion\ProductoStockRepository;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Domain\Ventas\CompradorRepository;
use App\Domain\Ventas\VentaProducto;
use App\Domain\Ventas\VentaProductoRepository;
use DateTimeImmutable;
use DomainException;

/**
 * Registra la venta de un producto terminado. Quien vende solo indica QUIÉN
 * compra (público general, un comprador registrado o un proveedor), el
 * producto, la cantidad y la forma de pago; el tipo de precio, el precio
 * unitario y el total los decide siempre este caso de uso
 * (TarifasProducto::aplicable), nunca el navegador:
 *
 *   proveedor registrado          → PROVEEDOR
 *   cantidad ≥ mínimo mayorista   → MAYORISTA
 *   cualquier otro caso           → PUBLICO
 *
 * El tipo y el precio aplicados quedan guardados en la venta como fotografía:
 * si mañana cambia el precio del producto, las ventas pasadas no cambian.
 *
 * DESCUENTO_PLANILLA solo vale para un proveedor y no puede superar el pago
 * semanal estimado que tiene disponible en el periodo en curso. Una venta al
 * CONTADO mete dinero real: en la misma transacción se registra su ENTRADA de
 * caja (RegistrarMovimientoCaja). La de planilla no, porque se cobra
 * descontándola del pago al proveedor.
 */
final class RegistrarVentaProducto
{
    public function __construct(
        private readonly ProductoRepository $productos,
        private readonly ObtenerTarifasProducto $tarifas,
        private readonly ProductoStockRepository $stock,
        private readonly ProductoMovimientoRepository $movimientos,
        private readonly ProveedorRepository $proveedores,
        private readonly CompradorRepository $compradores,
        private readonly PeriodoDescuentoActual $periodoActual,
        private readonly VentaProductoRepository $ventas,
        private readonly EstimarSaldoDisponibleProveedor $estimarSaldo,
        private readonly TransactionManager $transacciones,
        private readonly RegistrarMovimientoCaja $caja,
    ) {}

    /**
     * Sin proveedor ni comprador, la venta es al público general.
     */
    public function execute(
        int $productoId,
        float $cantidad,
        string $formaPago,
        int $registradoPorUserId,
        ?int $proveedorId = null,
        ?int $compradorId = null,
        ?DateTimeImmutable $fechaEvento = null,
        ?float $precioEsperado = null,
    ): VentaProducto {
        if ($proveedorId !== null && $compradorId !== null) {
            throw new DomainException('Una venta es para un proveedor o para un comprador registrado, no para ambos.');
        }

        $producto = $this->productos->buscarPorId($productoId)
            ?? throw new DomainException('El producto indicado no existe.');

        if (! $producto->estaActivo()) {
            throw new DomainException('El producto está inactivo: no se puede vender.');
        }

        $esProveedor = $proveedorId !== null;
        $cliente = $this->resolverCliente($proveedorId, $compradorId);

        if ($formaPago === 'DESCUENTO_PLANILLA' && ! $esProveedor) {
            throw new DomainException('El descuento de pago semanal solo aplica a ventas a un proveedor.');
        }

        $tarifas = $this->tarifas->execute($productoId);
        $precioVigente = $tarifas->aplicable($esProveedor, $cantidad)
            ?? throw new DomainException($this->motivoSinPrecio($esProveedor, $tarifas->minimoMayorista()));

        if ($precioEsperado !== null && abs($precioEsperado - $precioVigente->precio()) > 0.001) {
            throw new DomainException('El precio cambiÃ³. Revisa la venta antes de registrarla nuevamente.');
        }

        $importeTotal = round($cantidad * $precioVigente->precio(), 2);

        return $this->transacciones->run(function () use (
            $productoId, $proveedorId, $compradorId, $cantidad, $precioVigente,
            $importeTotal, $formaPago, $registradoPorUserId, $cliente, $producto, $fechaEvento,
        ): VentaProducto {
            // Se valida el saldo DENTRO de la transacción con la fila del
            // producto bloqueada, para que dos ventas simultáneas no vendan
            // la misma existencia.
            $this->stock->bloquearProducto($productoId);

            if ($cantidad > ($this->stock->buscarPorProductoId($productoId)?->cantidadActual() ?? 0.0)) {
                throw new DomainException('No hay stock suficiente del producto solicitado.');
            }

            $periodo = null;

            if ($formaPago === 'DESCUENTO_PLANILLA') {
                $periodo = $this->periodoActual->obtenerOCrear();

                if ($importeTotal > $this->estimarSaldo->execute($proveedorId, $periodo)) {
                    throw new DomainException('El monto del producto solicitado supera el pago estimado disponible del proveedor para este periodo.');
                }
            }

            $venta = $this->ventas->guardar(new VentaProducto(
                id: null,
                productoId: $productoId,
                proveedorId: $proveedorId,
                compradorId: $compradorId,
                tipoCliente: $precioVigente->tipoCliente(),
                cantidad: $cantidad,
                precioUnitario: $precioVigente->precio(),
                importeTotal: $importeTotal,
                formaPago: $formaPago,
                periodoId: $periodo?->id(),
                estado: 'REGISTRADA',
                fecha: $fechaEvento ?? new DateTimeImmutable,
                registradoPorUserId: $registradoPorUserId,
            ));

            $this->movimientos->guardar(new ProductoMovimiento(
                id: null,
                productoId: $productoId,
                tipo: 'VENTA',
                cantidad: $cantidad,
                loteProduccionId: null,
                ventaProductoId: $venta->id(),
                fecha: $fechaEvento ?? new DateTimeImmutable,
                registradoPorUserId: $registradoPorUserId,
            ));

            $this->caja->porVenta($venta, $cliente, $producto->nombre());

            return $venta;
        });
    }

    private function resolverCliente(?int $proveedorId, ?int $compradorId): string
    {
        if ($proveedorId !== null) {
            $proveedor = $this->proveedores->buscarPorId($proveedorId)
                ?? throw new DomainException('El proveedor indicado no existe.');

            if ($proveedor->estado() !== EstadoUsuario::ACTIVO) {
                throw new DomainException('El proveedor está inactivo: no se le pueden registrar compras.');
            }

            return $proveedor->nombreCompleto();
        }

        if ($compradorId !== null) {
            $comprador = $this->compradores->buscarPorId($compradorId)
                ?? throw new DomainException('El comprador indicado no existe.');

            if ($comprador->estado() !== 'ACTIVO') {
                throw new DomainException('El comprador está inactivo: no se le pueden registrar ventas.');
            }

            return $comprador->nombre();
        }

        return 'Público general';
    }

    private function motivoSinPrecio(bool $esProveedor, ?float $minimoMayorista): string
    {
        if ($esProveedor) {
            return 'Este producto no tiene un precio de proveedor vigente.';
        }

        if ($minimoMayorista !== null) {
            return 'Este producto no tiene precio público: solo se vende desde '.self::cantidad($minimoMayorista).' unidades (precio mayorista).';
        }

        return 'No existe un precio vigente configurado para este producto.';
    }

    private static function cantidad(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');
    }
}
