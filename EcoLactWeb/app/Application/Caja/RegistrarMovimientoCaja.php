<?php

namespace App\Application\Caja;

use App\Domain\Caja\MovimientoCaja;
use App\Domain\Caja\MovimientoCajaRepository;
use App\Domain\Compra\CategoriaCompra;
use App\Domain\Compra\PagoCompra;
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
 *   RegistrarPagoCompra       pago de una compra      → SALIDA  · INSUMOS o GASTOS
 *                             (contado, cuota o saldo; nunca lo que queda a crédito)
 *   caja:saldo-inicial        dinero con que arranca  → ENTRADA · SALDO_INICIAL (uno solo)
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
     * El dinero con que arranca la caja. Es ÚNICO (referencia SALDO_INICIAL #1):
     * si ya existe se devuelve el registrado y no se crea otro ni se modifica.
     */
    public function porSaldoInicial(float $monto, int $registradoPorUserId, ?string $observacion = null): MovimientoCaja
    {
        $monto = round($monto, 2);

        return $this->movimientos->guardarSiNoExiste(new MovimientoCaja(
            id: null,
            categoria: CategoriaMovimientoCaja::SALDO_INICIAL,
            monto: $monto,
            concepto: 'Saldo inicial de caja',
            descripcion: $observacion,
            referencia: 'SI-0001',
            detalle: array_filter([
                'Monto inicial' => 'S/ '.number_format($monto, 2),
                'Observación' => $observacion,
            ], fn (?string $valor): bool => $valor !== null),
            referenciaTipo: MovimientoCaja::REFERENCIA_SALDO_INICIAL,
            referenciaId: 1,
            registradoPorUserId: $registradoPorUserId,
            fecha: DateTimeImmutable::createFromInterface(now()),
        ));
    }

    /**
     * Un pago REAL de Compras y gastos: contado, cuota o saldo. Lo que se
     * compra a crédito no pasa por aquí hasta que se paga. Cada pago es un
     * movimiento propio (PAGO_COMPRA #pago), así 400 + 600 son dos salidas y
     * un reintento del mismo pago no crea una tercera.
     *
     * La categoría (INSUMOS o GASTOS) la decide el tipo de la compra.
     */
    public function porPagoCompra(CompraInsumo $compra, PagoCompra $pago, CategoriaCompra $categoria): MovimientoCaja
    {
        $codigo = $compra->codigo() ?? CompraInsumo::codigoPara((int) $compra->id());
        $comprobante = $compra->numeroComprobante() !== null ? $compra->referencia() : null;
        $total = $compra->total() ?? $pago->monto();
        $tipoPago = round($pago->monto(), 2) >= round($total, 2) ? 'Pago total' : 'Pago parcial';

        return $this->movimientos->guardarSiNoExiste(new MovimientoCaja(
            id: null,
            categoria: $categoria->categoriaCaja(),
            monto: $pago->monto(),
            concepto: $categoria->codigo() === CategoriaCompra::INSUMOS_PRODUCCION ? 'Compra de insumos' : 'Compra o gasto',
            descripcion: $comprobante ?? ($compra->proveedorComercial() ?? $compra->concepto() ?? 'Compra manual'),
            referencia: $codigo,
            detalle: array_filter([
                'Compra' => $codigo.($compra->concepto() !== null ? ' · '.$compra->concepto() : ''),
                'Tipo de compra' => $categoria->nombre(),
                'Comprobante' => $comprobante,
                'Proveedor comercial' => $compra->proveedorComercial(),
                'Fecha de compra' => $compra->fecha()->format('d/m/Y'),
                'Pago' => $tipoPago.' · '.$pago->codigo(),
                'Método de pago' => $pago->metodoPago()?->etiqueta(),
                'N.° de operación' => $pago->numeroOperacion(),
            ], fn (?string $valor): bool => $valor !== null),
            referenciaTipo: MovimientoCaja::REFERENCIA_PAGO_COMPRA,
            referenciaId: (int) $pago->id(),
            registradoPorUserId: $pago->registradoPorUserId(),
            fecha: $pago->fecha(),
        ));
    }
}
