<?php

use App\Application\Liquidacion\GenerarLiquidacion;
use App\Application\Liquidacion\ObtenerDetalleLiquidacion;
use App\Application\Liquidacion\RegistrarPago;
use App\Application\Liquidacion\ValorizarEntrega;
use App\Application\Ventas\RegistrarProductoPrecio;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(RefreshDatabase::class, TestCase::class);

/**
 * El periodo EcoLact real (jueves a miércoles) que contiene "hoy", igual al
 * que calcula RegistrarVentaProducto::resolverPeriodoActual() internamente:
 * la compra a descuento de planilla se valida contra el saldo estimado del
 * periodo ABIERTO que contiene la fecha actual, no cualquier fecha.
 *
 * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
 */
function odl_periodoActual(): array
{
    $hoy = new DateTimeImmutable;
    $diasDesdeJueves = ((int) $hoy->format('N') - 4 + 7) % 7;
    $fechaInicio = $hoy->modify("-{$diasDesdeJueves} days")->setTime(0, 0);

    return [$fechaInicio, $fechaInicio->modify('+6 days')];
}

it('arma el detalle con entregas, incidencia, compras, ajustes y saldo pendiente', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona, '11112222');
    $acopiador = fixture_acopiador($zona);
    $producto = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));
    fixture_regla_pago('OBSERVADA', 'MONTO', -0.20, new DateTimeImmutable('2026-01-01'));
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PROVEEDOR', 18.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);

    [$fechaInicio, $fechaFin] = odl_periodoActual();

    $entregaAceptada = fixture_entrega($proveedor, $acopiador, 30.0, $fechaInicio);
    fixture_analisis_confirmado($entregaAceptada, 'ACEPTADA', $admin->id);
    app(ValorizarEntrega::class)->execute($entregaAceptada->id);

    $entregaObservada = fixture_entrega($proveedor, $acopiador, 20.0, $fechaInicio->modify('+1 day'));
    fixture_analisis_confirmado($entregaObservada, 'OBSERVADA', $admin->id);
    app(ValorizarEntrega::class)->execute($entregaObservada->id);

    // La compra a descuento se registra MIENTRAS el periodo sigue abierto
    // (se valida contra el saldo ESTIMADO); GenerarLiquidacion detecta esta
    // venta por su periodo_id y anexa el ajuste automáticamente.
    app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        cantidad: 1.0,
        formaPago: 'DESCUENTO_PLANILLA',
        registradoPorUserId: $admin->id,
        proveedorId: $proveedor->id,
    );

    $liquidacion = app(GenerarLiquidacion::class)->execute(
        proveedorId: $proveedor->id,
        fechaInicio: $fechaInicio,
        fechaFin: $fechaFin,
        generadoPorUserId: $admin->id,
    );

    $saldoAntesDePagar = app(ObtenerDetalleLiquidacion::class)->execute($liquidacion->id())->saldoPendiente;
    app(RegistrarPago::class)->execute($liquidacion->id(), $saldoAntesDePagar, $admin->id);

    $detalle = app(ObtenerDetalleLiquidacion::class)->execute($liquidacion->id());

    expect($detalle)->not->toBeNull();
    expect($detalle->proveedorDni)->toBe('11112222');
    expect($detalle->entregas)->toHaveCount(2);
    expect($detalle->incidencia)->not->toBeNull();
    expect($detalle->incidencia['resultado'])->toBe('OBSERVADA');
    expect($detalle->compras)->toHaveCount(1);
    expect($detalle->ajustes)->toHaveCount(1);
    expect($detalle->pagos)->toHaveCount(1);
    expect($detalle->saldoPendiente)->toBe(0.0);
});

it('devuelve null para una liquidación que no existe', function () {
    expect(app(ObtenerDetalleLiquidacion::class)->execute(999999))->toBeNull();
});
