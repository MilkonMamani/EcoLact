<?php

use App\Application\Liquidacion\GenerarLiquidacion;
use App\Application\Liquidacion\ValorizarEntrega;
use App\Application\Ventas\RegistrarComprador;
use App\Application\Ventas\RegistrarProductoPrecio;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Domain\Produccion\ProductoStockRepository;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\ProductoMovimientoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function rvp_crearProducto(): ProductoModel
{
    return ProductoModel::create([
        'nombre' => 'Queso Paria '.uniqid(),
        'unidad_medida' => 'UNIDAD',
        'estado' => 'ACTIVO',
    ]);
}

/**
 * El periodo EcoLact real (jueves a miércoles) que contiene "hoy", igual al
 * que calcula RegistrarVentaProducto::resolverPeriodoActual() internamente.
 * Las entregas de las pruebas de DESCUENTO_PLANILLA deben caer dentro de
 * este rango para que la estimación de saldo las encuentre.
 *
 * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
 */
function rvp_periodoActual(): array
{
    $hoy = new DateTimeImmutable;
    $diasDesdeJueves = ((int) $hoy->format('N') - 4 + 7) % 7;
    $fechaInicio = $hoy->modify("-{$diasDesdeJueves} days")->setTime(0, 0);

    return [$fechaInicio, $fechaInicio->modify('+6 days')];
}

it('vende al público al contado, descuenta el stock y registra el movimiento', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = rvp_crearProducto();

    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PUBLICO', 20.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);

    $venta = app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        tipoCliente: 'PUBLICO',
        cantidad: 3.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
    );

    expect($venta->precioUnitario())->toBe(20.0);
    expect($venta->importeTotal())->toBe(60.0);

    expect(app(ProductoStockRepository::class)->buscarPorProductoId($producto->id)->cantidadActual())->toBe(7.0);
    expect(ProductoMovimientoModel::where('producto_id', $producto->id)->where('tipo', 'VENTA')->exists())->toBeTrue();
});

it('no permite vender sin un precio vigente configurado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = rvp_crearProducto();
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);

    app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        tipoCliente: 'PUBLICO',
        cantidad: 1.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
    );
})->throws(DomainException::class, 'No existe un precio vigente configurado para este producto y tipo de cliente.');

it('no permite vender más de lo que hay en stock', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = rvp_crearProducto();

    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PUBLICO', 20.0, $admin->id);
    fixture_ingreso_producto($producto->id, 2.0, $admin->id);

    app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        tipoCliente: 'PUBLICO',
        cantidad: 5.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
    );
})->throws(DomainException::class, 'No hay stock suficiente del producto solicitado.');

it('registra la venta a un mayorista identificado por un comprador', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = rvp_crearProducto();

    app(RegistrarProductoPrecio::class)->execute($producto->id, 'MAYORISTA', 19.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);
    $comprador = app(RegistrarComprador::class)->execute('Distribuidora XYZ', 'MAYORISTA');

    $venta = app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        tipoCliente: 'MAYORISTA',
        cantidad: 4.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
        compradorId: $comprador->id(),
    );

    expect($venta->importeTotal())->toBe(76.0);
    expect($venta->compradorId())->toBe($comprador->id());
});

it('permite a un proveedor comprar a descuento de planilla dentro de su pago estimado', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = rvp_crearProducto();

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PROVEEDOR', 18.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);

    // 100 litros ACEPTADA esta semana -> pago estimado 170.00.
    [$fechaInicio] = rvp_periodoActual();
    $entrega = fixture_entrega($proveedor, $acopiador, 100.0, $fechaInicio);
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $admin->id);

    $venta = app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        tipoCliente: 'PROVEEDOR',
        cantidad: 5.0, // 5 x 18 = 90, dentro del saldo de 170.
        formaPago: 'DESCUENTO_PLANILLA',
        registradoPorUserId: $admin->id,
        proveedorId: $proveedor->id,
    );

    expect($venta->importeTotal())->toBe(90.0);
    expect($venta->periodoId())->not->toBeNull();
});

it('no permite a un proveedor comprar a descuento de planilla por encima de su pago estimado', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = rvp_crearProducto();

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PROVEEDOR', 18.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);

    // 10 litros ACEPTADA esta semana -> pago estimado 17.00.
    [$fechaInicio] = rvp_periodoActual();
    $entrega = fixture_entrega($proveedor, $acopiador, 10.0, $fechaInicio);
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $admin->id);

    app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        tipoCliente: 'PROVEEDOR',
        cantidad: 5.0, // 5 x 18 = 90, muy por encima del saldo de 17.
        formaPago: 'DESCUENTO_PLANILLA',
        registradoPorUserId: $admin->id,
        proveedorId: $proveedor->id,
    );
})->throws(DomainException::class, 'El monto del producto solicitado supera el pago estimado disponible del proveedor para este periodo.');

it('al generar la liquidación, descuenta automáticamente las compras a cuenta del pago', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = rvp_crearProducto();

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PROVEEDOR', 18.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);

    [$fechaInicio, $fechaFin] = rvp_periodoActual();
    $entrega = fixture_entrega($proveedor, $acopiador, 100.0, $fechaInicio);
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $admin->id);
    app(ValorizarEntrega::class)->execute($entrega->id);

    app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        tipoCliente: 'PROVEEDOR',
        cantidad: 5.0, // 5 x 18 = 90
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

    // 100 litros x 1.70 = 170.00, menos 90.00 de la compra de queso = 80.00.
    expect($liquidacion->subtotal())->toBe(170.0);
    expect($liquidacion->totalAjustes())->toBe(-90.0);
    expect($liquidacion->totalPagar())->toBe(80.0);
});
