<?php

namespace App\Application\Caja;

use App\Domain\Caja\MovimientoCaja;
use App\Domain\Caja\MovimientoCajaRepository;
use App\Domain\Pago\ObligacionPago;
use App\Domain\Pago\PagoRealizado;
use App\Domain\Pago\PeriodoPago;
use App\Domain\Produccion\CompraInsumo;
use App\Domain\Shared\Enums\CategoriaMovimientoCaja;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Domain\Ventas\VentaProducto;
use DateTimeImmutable;

/**
 * La ÚNICA puerta de la caja. La llaman los casos de uso que mueven dinero
 * real, DENTRO de su propia transacción, justo después de confirmar la
 * operación (si la caja falla, la operación tampoco queda registrada):
 *
 *   RegistrarVentaProducto    venta al CONTADO        → ENTRADA · VENTA
 *   RegistrarPagoObligacion   pago a un proveedor     → SALIDA  · PROVEEDOR
 *                             pago a un trabajador    → SALIDA  · TRABAJADOR
 *   RegistrarCompraInsumos    compra con total        → SALIDA  · INSUMOS
 *
 * No abre transacción propia ni decide nada del negocio: solo fotografía la
 * operación. Es idempotente por (referencia_tipo, referencia_id).
 */
final class RegistrarMovimientoCaja
{
    public function __construct(
        private readonly MovimientoCajaRepository $movimientos,
    ) {}

    /**
     * Una venta en DESCUENTO_PLANILLA no mete dinero en la caja: se cobra
     * descontándola del pago semanal del proveedor (que sale ya neto).
     */
    public function porVenta(VentaProducto $venta, string $cliente, string $producto): ?MovimientoCaja
    {
        if ($venta->formaPago() !== 'CONTADO') {
            return null;
        }

        $id = (int) $venta->id();
        $codigo = VentaProducto::codigo($id);
        $cantidad = rtrim(rtrim(number_format($venta->cantidad(), 2, '.', ''), '0'), '.');

        return $this->movimientos->guardarSiNoExiste(new MovimientoCaja(
            id: null,
            categoria: CategoriaMovimientoCaja::VENTA,
            monto: $venta->importeTotal(),
            concepto: 'Venta de productos',
            descripcion: $cliente,
            referencia: $codigo,
            detalle: [
                'Comprador' => $cliente,
                'Producto' => $producto.' × '.$cantidad,
                'Forma de pago' => 'Contado',
                'Referencia' => 'Venta '.$codigo,
            ],
            referenciaTipo: MovimientoCaja::REFERENCIA_VENTA,
            referenciaId: $id,
            registradoPorUserId: $venta->registradoPorUserId(),
            fecha: $venta->fecha(),
        ));
    }

    public function porPago(ObligacionPago $obligacion, PagoRealizado $pago): MovimientoCaja
    {
        $id = (int) $pago->id();
        $periodo = PeriodoPago::etiquetaDe($obligacion->periodicidad(), $obligacion->periodoInicio());
        $fecha = $pago->pagadoAt() ?? DateTimeImmutable::createFromInterface(now());
        $metodo = $pago->metodoPago()->etiqueta();

        if ($obligacion->tipoBeneficiario() === TipoBeneficiarioPago::PROVEEDOR) {
            $referencia = 'Liquidación SEM-'.$obligacion->periodoInicio()->modify('+6 days')->format('W');

            return $this->movimientos->guardarSiNoExiste(new MovimientoCaja(
                id: null,
                categoria: CategoriaMovimientoCaja::PROVEEDOR,
                monto: $pago->monto(),
                concepto: 'Pago a proveedor',
                descripcion: $obligacion->beneficiarioNombre(),
                referencia: $referencia,
                detalle: array_filter([
                    'Proveedor' => $obligacion->beneficiarioNombre(),
                    'Semana' => $periodo.' ('.$obligacion->periodoInicio()->format('d/m').' – '.$obligacion->periodoFin()->format('d/m/Y').')',
                    'Método de pago' => $metodo,
                    'Lote de pago' => $pago->loteCodigo(),
                    'Referencia' => $referencia,
                ], fn (?string $valor): bool => $valor !== null),
                referenciaTipo: MovimientoCaja::REFERENCIA_PAGO_PROVEEDOR,
                referenciaId: $id,
                registradoPorUserId: $pago->registradoPorUserId(),
                fecha: $fecha,
            ));
        }

        $codigo = 'P-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT);

        return $this->movimientos->guardarSiNoExiste(new MovimientoCaja(
            id: null,
            categoria: CategoriaMovimientoCaja::TRABAJADOR,
            monto: $pago->monto(),
            concepto: 'Pago a trabajador',
            descripcion: $obligacion->beneficiarioNombre(),
            referencia: 'Planilla '.mb_strtolower($periodo),
            detalle: array_filter([
                'Trabajador' => $obligacion->beneficiarioNombre(),
                'Periodo' => $periodo,
                'Método de pago' => $metodo,
                'N.° de operación' => $pago->numeroOperacion(),
                'Referencia' => 'Pago '.$codigo,
            ], fn (?string $valor): bool => $valor !== null),
            referenciaTipo: MovimientoCaja::REFERENCIA_PAGO_TRABAJADOR,
            referenciaId: $id,
            registradoPorUserId: $pago->registradoPorUserId(),
            fecha: $fecha,
        ));
    }

    /**
     * Sin total no se sabe cuánto dinero salió: la compra se registra en el
     * inventario, pero no genera salida de caja.
     */
    public function porCompraInsumos(CompraInsumo $compra): ?MovimientoCaja
    {
        if ($compra->total() === null || round($compra->total(), 2) <= 0.0) {
            return null;
        }

        $id = (int) $compra->id();
        $codigo = 'C-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT);
        $boleta = $compra->numeroComprobante();

        return $this->movimientos->guardarSiNoExiste(new MovimientoCaja(
            id: null,
            categoria: CategoriaMovimientoCaja::INSUMOS,
            monto: $compra->total(),
            concepto: 'Compra de insumos',
            descripcion: $boleta !== null ? 'Boleta '.$boleta : ($compra->proveedorComercial() ?? 'Compra manual'),
            referencia: $codigo,
            detalle: array_filter([
                'Boleta' => $boleta,
                'Proveedor comercial' => $compra->proveedorComercial(),
                'Fecha de compra' => $compra->fecha()->format('d/m/Y'),
                'Referencia' => 'Compra '.$codigo,
            ], fn (?string $valor): bool => $valor !== null),
            referenciaTipo: MovimientoCaja::REFERENCIA_COMPRA_INSUMOS,
            referenciaId: $id,
            registradoPorUserId: $compra->registradoPorUserId(),
            fecha: DateTimeImmutable::createFromInterface(now()),
        ));
    }
}
