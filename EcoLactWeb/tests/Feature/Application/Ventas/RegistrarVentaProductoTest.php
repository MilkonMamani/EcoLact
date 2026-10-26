<?php

use App\Application\Caja\RegistrarMovimientoCaja;
use App\Application\Liquidacion\GenerarLiquidacion;
use App\Application\Liquidacion\ValorizarEntrega;
use App\Application\Ventas\ConfigurarPreciosProducto;
use App\Application\Ventas\RegistrarComprador;
use App\Application\Ventas\RegistrarProductoPrecio;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Domain\Produccion\ProductoStockRepository;
use App\Infrastructure\Persistence\Models\CompradorModel;
use App\Infrastructure\Persistence\Models\MovimientoCajaModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\ProductoMovimientoModel;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use App\Infrastructure\Persistence\Repositories\EloquentVentaProductoRepository;
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
 * Queso con los precios del ejemplo de Productos: PROVEEDOR 4.00, PUBLICO 5.00
 * y MAYORISTA 4.50 desde 12 unidades; con $stock unidades en inventario.
 */
function rvp_quesoConTarifas(int $adminId, float $stock = 20.0): ProductoModel
{
    $producto = rvp_crearProducto();
    app(ConfigurarPreciosProducto::class)->execute($producto->id, 4.0, 5.0, 4.5, 12.0, $adminId);
    fixture_ingreso_producto($producto->id, $stock, $adminId);

    return $producto;
}

/**
 * El periodo EcoLact real (jueves a miércoles) que contiene "hoy", igual al
 * que calcula PeriodoDescuentoActual. Las entregas de las pruebas de
 * DESCUENTO_PLANILLA deben caer dentro de este rango para que la estimación
 * de saldo las encuentre.
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

/** Proveedor con 100 litros ACEPTADA esta semana a S/ 1.70 → pago estimado S/ 170.00 (o $litros × 1.70). */
function rvp_proveedorConPagoSemanal(int $adminId, float $litros = 100.0): object
{
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $adminId);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));
    [$fechaInicio] = rvp_periodoActual();
    $entrega = fixture_entrega($proveedor, fixture_acopiador($zona), $litros, $fechaInicio);
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $adminId);

    return (object) ['proveedor' => $proveedor, 'entrega' => $entrega];
}

it('vende al público al contado, descuenta el stock y registra el movimiento', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = rvp_crearProducto();

    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PUBLICO', 20.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);

    $venta = app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        cantidad: 3.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
    );

    expect($venta->precioUnitario())->toBe(20.0);
    expect($venta->importeTotal())->toBe(60.0);

    expect(app(ProductoStockRepository::class)->buscarPorProductoId($producto->id)->cantidadActual())->toBe(7.0);
    expect(ProductoMovimientoModel::where('producto_id', $producto->id)->where('tipo', 'VENTA')->exists())->toBeTrue();
});

it('CASO A: público general por debajo del mínimo mayorista paga precio PUBLICO', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $queso = rvp_quesoConTarifas($admin->id);

    $venta = app(RegistrarVentaProducto::class)->execute(productoId: $queso->id, cantidad: 2.0, formaPago: 'CONTADO', registradoPorUserId: $admin->id);

    expect($venta->tipoCliente())->toBe('PUBLICO');
    expect($venta->precioUnitario())->toBe(5.0);
    expect($venta->importeTotal())->toBe(10.0);
});

it('CASO B: un comprador registrado que llega al mínimo paga precio MAYORISTA', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $queso = rvp_quesoConTarifas($admin->id);
    $bodega = app(RegistrarComprador::class)->execute('Bodega Central');

    $venta = app(RegistrarVentaProducto::class)->execute(productoId: $queso->id, cantidad: 12.0, formaPago: 'CONTADO', registradoPorUserId: $admin->id, compradorId: $bodega->id());

    expect($venta->tipoCliente())->toBe('MAYORISTA');
    expect($venta->precioUnitario())->toBe(4.5);
    expect($venta->importeTotal())->toBe(54.0);
    expect($venta->compradorId())->toBe($bodega->id());
});

it('CASO C: un comprador registrado por debajo del mínimo paga PUBLICO, no MAYORISTA', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $queso = rvp_quesoConTarifas($admin->id);
    $bodega = app(RegistrarComprador::class)->execute('Bodega Central');

    $venta = app(RegistrarVentaProducto::class)->execute(productoId: $queso->id, cantidad: 11.0, formaPago: 'CONTADO', registradoPorUserId: $admin->id, compradorId: $bodega->id());

    expect($venta->tipoCliente())->toBe('PUBLICO');
    expect($venta->precioUnitario())->toBe(5.0);
});

it('un comprador histórico marcado como MAYORISTA ya no fija el precio: manda la cantidad', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $queso = rvp_quesoConTarifas($admin->id);
    $historico = CompradorModel::create(['nombre' => 'Mayorista Antiguo', 'tipo_cliente' => 'MAYORISTA', 'estado' => 'ACTIVO']);

    $venta = app(RegistrarVentaProducto::class)->execute(productoId: $queso->id, cantidad: 3.0, formaPago: 'CONTADO', registradoPorUserId: $admin->id, compradorId: $historico->id);

    expect($venta->tipoCliente())->toBe('PUBLICO');
});

it('CASO D: un proveedor paga precio PROVEEDOR aunque compre más del mínimo mayorista', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $queso = rvp_quesoConTarifas($admin->id);
    $proveedor = fixture_proveedor(fixture_zona());

    $venta = app(RegistrarVentaProducto::class)->execute(productoId: $queso->id, cantidad: 20.0, formaPago: 'CONTADO', registradoPorUserId: $admin->id, proveedorId: $proveedor->id);

    expect($venta->tipoCliente())->toBe('PROVEEDOR');
    expect($venta->precioUnitario())->toBe(4.0);
    expect($venta->importeTotal())->toBe(80.0);
});

it('CASO E: vender 12 de 20 deja 8 en stock', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $queso = rvp_quesoConTarifas($admin->id, stock: 20.0);

    app(RegistrarVentaProducto::class)->execute(productoId: $queso->id, cantidad: 12.0, formaPago: 'CONTADO', registradoPorUserId: $admin->id);

    expect(app(ProductoStockRepository::class)->buscarPorProductoId($queso->id)->cantidadActual())->toBe(8.0);
});

it('CASO F: no permite vender más de lo que hay en stock y no deja rastro', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $queso = rvp_quesoConTarifas($admin->id, stock: 20.0);

    expect(fn () => app(RegistrarVentaProducto::class)->execute(productoId: $queso->id, cantidad: 21.0, formaPago: 'CONTADO', registradoPorUserId: $admin->id))
        ->toThrow(DomainException::class, 'No hay stock suficiente del producto solicitado.');

    expect(VentaProductoModel::count())->toBe(0);
    expect(MovimientoCajaModel::count())->toBe(0);
    expect(app(ProductoStockRepository::class)->buscarPorProductoId($queso->id)->cantidadActual())->toBe(20.0);
});

it('CASO G: una venta al contado genera UNA sola ENTRADA de caja por su importe', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $queso = rvp_quesoConTarifas($admin->id);

    $venta = app(RegistrarVentaProducto::class)->execute(productoId: $queso->id, cantidad: 12.0, formaPago: 'CONTADO', registradoPorUserId: $admin->id);

    $movimiento = MovimientoCajaModel::sole();
    expect($movimiento->tipo)->toBe('ENTRADA');
    expect($movimiento->categoria)->toBe('VENTA');
    expect((float) $movimiento->monto)->toBe(54.0);
    expect($movimiento->referencia_tipo)->toBe('VENTA');
    expect($movimiento->referencia_id)->toBe($venta->id());

    // Reintentar el registro de caja de la misma venta no la duplica.
    app(RegistrarMovimientoCaja::class)->porVenta($venta, 'Público general', 'Queso');
    expect(MovimientoCajaModel::count())->toBe(1);
});

it('CASO H: un proveedor compra a descuento de planilla: baja el stock, la caja no cambia y queda para su liquidación', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $queso = rvp_crearProducto();
    app(RegistrarProductoPrecio::class)->execute($queso->id, 'PROVEEDOR', 4.0, $admin->id);
    fixture_ingreso_producto($queso->id, 20.0, $admin->id);
    $proveedor = rvp_proveedorConPagoSemanal($admin->id)->proveedor;

    $venta = app(RegistrarVentaProducto::class)->execute(productoId: $queso->id, cantidad: 12.0, formaPago: 'DESCUENTO_PLANILLA', registradoPorUserId: $admin->id, proveedorId: $proveedor->id);

    expect($venta->importeTotal())->toBe(48.0);
    expect($venta->periodoId())->not->toBeNull();
    expect(app(ProductoStockRepository::class)->buscarPorProductoId($queso->id)->cantidadActual())->toBe(8.0);
    expect(MovimientoCajaModel::count())->toBe(0);
    expect(app(EloquentVentaProductoRepository::class)->listarPorProveedorYPeriodo($proveedor->id, $venta->periodoId(), 'DESCUENTO_PLANILLA'))->toHaveCount(1);
});

it('CASO I: no permite a un proveedor comprar a descuento de planilla por encima de su pago estimado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = rvp_crearProducto();
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PROVEEDOR', 18.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);
    // 10 litros ACEPTADA esta semana -> pago estimado 17.00.
    $proveedor = rvp_proveedorConPagoSemanal($admin->id, litros: 10.0)->proveedor;

    app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        cantidad: 5.0, // 5 x 18 = 90, muy por encima del saldo de 17.
        formaPago: 'DESCUENTO_PLANILLA',
        registradoPorUserId: $admin->id,
        proveedorId: $proveedor->id,
    );
})->throws(DomainException::class, 'El monto del producto solicitado supera el pago estimado disponible del proveedor para este periodo.');

it('el descuento de planilla solo vale para proveedores', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $queso = rvp_quesoConTarifas($admin->id);

    app(RegistrarVentaProducto::class)->execute(productoId: $queso->id, cantidad: 1.0, formaPago: 'DESCUENTO_PLANILLA', registradoPorUserId: $admin->id);
})->throws(DomainException::class, 'El descuento de pago semanal solo aplica a ventas a un proveedor.');

it('la venta guarda el precio y el tipo aplicados: cambiar el precio después no la altera', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $queso = rvp_quesoConTarifas($admin->id);
    $venta = app(RegistrarVentaProducto::class)->execute(productoId: $queso->id, cantidad: 12.0, formaPago: 'CONTADO', registradoPorUserId: $admin->id);

    app(ConfigurarPreciosProducto::class)->execute($queso->id, 4.0, 6.0, 5.5, 20.0, $admin->id);

    $guardada = VentaProductoModel::findOrFail($venta->id());
    expect($guardada->tipo_cliente)->toBe('MAYORISTA');
    expect((float) $guardada->precio_unitario)->toBe(4.5);
    expect((float) $guardada->importe_total)->toBe(54.0);
});

it('no permite vender sin un precio vigente configurado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = rvp_crearProducto();
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);

    app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        cantidad: 1.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
    );
})->throws(DomainException::class, 'No existe un precio vigente configurado para este producto.');

it('sin precio público, solo se vende al público desde el mínimo mayorista', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = rvp_crearProducto();
    app(ConfigurarPreciosProducto::class)->execute($producto->id, null, null, 4.5, 12.0, $admin->id);
    fixture_ingreso_producto($producto->id, 20.0, $admin->id);

    expect(fn () => app(RegistrarVentaProducto::class)->execute(productoId: $producto->id, cantidad: 5.0, formaPago: 'CONTADO', registradoPorUserId: $admin->id))
        ->toThrow(DomainException::class, 'Este producto no tiene precio público: solo se vende desde 12 unidades (precio mayorista).');

    expect(app(RegistrarVentaProducto::class)->execute(productoId: $producto->id, cantidad: 12.0, formaPago: 'CONTADO', registradoPorUserId: $admin->id)->tipoCliente())->toBe('MAYORISTA');
});

it('una venta es para un proveedor o para un comprador, no para ambos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $queso = rvp_quesoConTarifas($admin->id);

    app(RegistrarVentaProducto::class)->execute(
        productoId: $queso->id,
        cantidad: 1.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
        proveedorId: fixture_proveedor(fixture_zona())->id,
        compradorId: app(RegistrarComprador::class)->execute('Bodega Central')->id(),
    );
})->throws(DomainException::class, 'Una venta es para un proveedor o para un comprador registrado, no para ambos.');

it('permite a un proveedor comprar a descuento de planilla dentro de su pago estimado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = rvp_crearProducto();
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PROVEEDOR', 18.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);
    $proveedor = rvp_proveedorConPagoSemanal($admin->id)->proveedor;

    $venta = app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        cantidad: 5.0, // 5 x 18 = 90, dentro del saldo de 170.
        formaPago: 'DESCUENTO_PLANILLA',
        registradoPorUserId: $admin->id,
        proveedorId: $proveedor->id,
    );

    expect($venta->importeTotal())->toBe(90.0);
    expect($venta->periodoId())->not->toBeNull();
});

it('al generar la liquidación, descuenta automáticamente las compras a cuenta del pago', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = rvp_crearProducto();
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PROVEEDOR', 18.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);
    $semana = rvp_proveedorConPagoSemanal($admin->id);
    app(ValorizarEntrega::class)->execute($semana->entrega->id);
    [$fechaInicio, $fechaFin] = rvp_periodoActual();

    app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        cantidad: 5.0, // 5 x 18 = 90
        formaPago: 'DESCUENTO_PLANILLA',
        registradoPorUserId: $admin->id,
        proveedorId: $semana->proveedor->id,
    );

    $liquidacion = app(GenerarLiquidacion::class)->execute(
        proveedorId: $semana->proveedor->id,
        fechaInicio: $fechaInicio,
        fechaFin: $fechaFin,
        generadoPorUserId: $admin->id,
    );

    // 100 litros x 1.70 = 170.00, menos 90.00 de la compra de queso = 80.00.
    expect($liquidacion->subtotal())->toBe(170.0);
    expect($liquidacion->totalAjustes())->toBe(-90.0);
    expect($liquidacion->totalPagar())->toBe(80.0);
});

it('no permite venderle a un proveedor inactivo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = rvp_crearProducto();
    $proveedor = fixture_proveedor(fixture_zona());
    $proveedor->update(['estado' => 'INACTIVO']);

    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PROVEEDOR', 20.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);

    app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        cantidad: 1.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
        proveedorId: $proveedor->id,
    );
})->throws(DomainException::class, 'El proveedor está inactivo: no se le pueden registrar compras.');
