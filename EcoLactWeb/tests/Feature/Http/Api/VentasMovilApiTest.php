<?php

use App\Application\Ventas\ConfigurarPreciosProducto;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Infrastructure\Persistence\Models\ProductoMovimientoModel;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function escenarioVentasMovil(): array
{
    $admin = fixture_usuario('ADMINISTRADOR');
    $vendedor = fixture_usuario('VENTAS');
    fixture_presente_hoy($vendedor);
    $producto = fixture_producto('Queso Paria 1 kg');
    app(ConfigurarPreciosProducto::class)->execute($producto->id, 4.0, 5.0, 4.5, 12.0, $admin->id);
    fixture_ingreso_producto($producto->id, 120, $admin->id);

    return [$vendedor, $producto, $admin];
}

it('cotiza usando la cantidad sin aceptar un precio impuesto por el vendedor', function (int $cantidad, string $tipo, float $precio, float $total) {
    [$user, $producto] = escenarioVentasMovil();

    $this->actingAs($user, 'sanctum')->postJson(route('api.ventas.cotizar'), [
        'cliente' => 'PUBLICO', 'producto_id' => $producto->id, 'cantidad' => $cantidad,
        'forma_pago' => 'CONTADO', 'precio_unitario' => 0.01, 'tipo_precio' => 'PROVEEDOR',
    ])->assertOk()->assertJsonPath('data.tipo_cliente', $tipo)
        ->assertJsonPath('data.precio_unitario', fn ($valor): bool => (float) $valor === $precio)
        ->assertJsonPath('data.importe_total', fn ($valor): bool => (float) $valor === $total);
    $this->assertDatabaseCount('ventas_producto', 0);
})->with(['publico' => [5, 'PUBLICO', 5.0, 25.0], 'mayorista' => [12, 'MAYORISTA', 4.5, 54.0]]);

it('cotiza proveedor a cuatro soles y registra efectivo sin cambiar el precio por cantidad', function () {
    [$user, $producto] = escenarioVentasMovil();
    $proveedor = fixture_proveedor(fixture_zona());

    $this->actingAs($user, 'sanctum')->postJson(route('api.ventas.ventas.store'), [
        'cliente' => 'PROVEEDOR', 'proveedor_id' => $proveedor->id,
        'producto_id' => $producto->id, 'cantidad' => 2, 'forma_pago' => 'CONTADO',
    ])->assertCreated()->assertJsonPath('data.tipo_cliente', 'PROVEEDOR')->assertJsonPath('data.importe_total', 8);
    $this->assertDatabaseHas('ventas_producto', ['proveedor_id' => $proveedor->id, 'importe_total' => 8]);
    $this->assertDatabaseCount('movimientos_caja', 1);
});

it('reintenta una venta una sola vez y conserva la fecha real del evento', function () {
    [$user, $producto] = escenarioVentasMovil();
    $evento = now()->subDay()->setTime(15, 15)->toIso8601String();
    fixture_presente_hoy($user, CarbonImmutable::parse($evento)->timezone(config('app.zona_horaria_negocio'))->toDateString());
    $datos = ['cliente' => 'PUBLICO', 'producto_id' => $producto->id, 'cantidad' => 5,
        'forma_pago' => 'CONTADO', 'fecha_hora_evento' => $evento, 'precio_mostrado' => 5];
    $headers = ['Idempotency-Key' => 'venta-local-abc'];

    $primera = $this->actingAs($user, 'sanctum')->postJson(route('api.ventas.ventas.store'), $datos, $headers)->assertCreated();
    $this->postJson(route('api.ventas.ventas.store'), $datos, $headers)->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('data.id', $primera->json('data.id'));

    $this->assertDatabaseCount('ventas_producto', 1);
    $this->assertDatabaseCount('movimientos_caja', 1);
    // El stock del producto se descuenta una sola vez.
    expect(ProductoMovimientoModel::where('tipo', 'VENTA')->count())->toBe(1);
    expect(VentaProductoModel::sole()->fecha->timestamp)->toBe(strtotime($evento));
});

it('rechaza precio cambiado y stock insuficiente sin crear venta ni caja', function (int $cantidad, float $precio) {
    [$user, $producto] = escenarioVentasMovil();

    $this->actingAs($user, 'sanctum')->postJson(route('api.ventas.ventas.store'), [
        'cliente' => 'PUBLICO', 'producto_id' => $producto->id, 'cantidad' => $cantidad,
        'forma_pago' => 'CONTADO', 'precio_mostrado' => $precio,
    ])->assertUnprocessable()->assertJsonValidationErrors('venta');
    $this->assertDatabaseCount('ventas_producto', 0);
    $this->assertDatabaseCount('movimientos_caja', 0);
})->with(['precio' => [5, 4.0], 'stock' => [121, 4.5]]);

it('no admite descuento semanal para publico ni otros medios de pago', function (string $forma) {
    [$user, $producto] = escenarioVentasMovil();

    $this->actingAs($user, 'sanctum')->postJson(route('api.ventas.ventas.store'), [
        'cliente' => 'PUBLICO', 'producto_id' => $producto->id, 'cantidad' => 1, 'forma_pago' => $forma,
    ])->assertUnprocessable();
    $this->assertDatabaseCount('ventas_producto', 0);
})->with(['DESCUENTO_PLANILLA', 'YAPE', 'PLIN', 'TARJETA', 'CREDITO']);

it('limita historial y detalle al vendedor autenticado', function () {
    [$user, $producto, $admin] = escenarioVentasMovil();
    $otra = app(RegistrarVentaProducto::class)->execute($producto->id, 1, 'CONTADO', $admin->id);
    $propia = app(RegistrarVentaProducto::class)->execute($producto->id, 2, 'CONTADO', $user->id);

    $this->actingAs($user, 'sanctum')->getJson(route('api.ventas.movil'))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $propia->id())
        ->assertJsonPath('data.0.cliente', 'Público general')->assertJsonPath('data.0.documento', null);
    $this->getJson(route('api.ventas.ventas.show', $otra->id()))->assertForbidden();
    $this->getJson(route('api.ventas.ventas.index'))->assertOk()->assertJsonCount(1, 'data.filas');
});

it('permite consultar sin asistencia pero bloquea ventas y nuevos compradores', function () {
    $user = fixture_usuario('VENTAS');
    $this->actingAs($user, 'sanctum')->getJson(route('api.ventas.catalogo'))->assertOk();
    $this->getJson(route('api.ventas.movil'))->assertOk();
    $this->getJson(route('api.ventas.mi-asistencia'))->assertOk();
    $this->postJson(route('api.ventas.ventas.store'), [])->assertForbidden()->assertJsonPath('code', 'ASISTENCIA_REQUERIDA');
    $this->postJson(route('api.ventas.compradores.store'), ['nombre' => 'Cliente'])->assertForbidden();
    $this->assertDatabaseCount('ventas_producto', 0);
    $this->assertDatabaseCount('compradores', 0);
});

it('rechaza visitantes y otros roles en el catalogo movil', function () {
    $this->getJson(route('api.ventas.catalogo'))->assertUnauthorized();
    $user = fixture_usuario('CALIDAD');
    $this->actingAs($user, 'sanctum')->getJson(route('api.ventas.catalogo'))->assertForbidden();
});

it('ventas no accede a caja pagos ni configuracion de precios', function () {
    [$user, $producto] = escenarioVentasMovil();
    $this->actingAs($user)->get(route('admin.caja.index'))->assertForbidden();
    $this->get(route('admin.pagos.proveedores.index'))->assertForbidden();
    $this->get(route('admin.pagos.trabajadores.index'))->assertForbidden();
    $this->put(route('admin.producto-precios.update', $producto->id), ['precio_publico' => 1])->assertForbidden();
});

it('registra descuento semanal del proveedor sin entrada de caja', function () {
    [$user, $producto, $admin] = escenarioVentasMovil();
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));
    $entrega = fixture_entrega($proveedor, fixture_acopiador($zona), 100, new DateTimeImmutable);
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $admin->id);
    $datos = ['cliente' => 'PROVEEDOR', 'proveedor_id' => $proveedor->id,
        'producto_id' => $producto->id, 'cantidad' => 2, 'forma_pago' => 'DESCUENTO_PLANILLA'];

    $this->actingAs($user, 'sanctum')->postJson(route('api.ventas.cotizar'), $datos)
        ->assertOk()->assertJsonPath('data.importe_total', 8);
    $this->postJson(route('api.ventas.ventas.store'), $datos, ['Idempotency-Key' => 'descuento-local'])
        ->assertCreated()->assertJsonPath('data.importe_total', 8);
    $this->assertDatabaseCount('movimientos_caja', 0);
    $this->assertDatabaseHas('ventas_producto', ['proveedor_id' => $proveedor->id, 'forma_pago' => 'DESCUENTO_PLANILLA', 'importe_total' => 8]);
});

it('registra compradores con documento real sin pedir un tipo de precio', function () {
    [$user] = escenarioVentasMovil();

    $this->actingAs($user, 'sanctum')->postJson(route('api.ventas.compradores.store'), [
        'nombre' => 'Distribuidora San Miguel SAC', 'documento' => '20601234567', 'telefono' => '987654321',
    ])->assertCreated()->assertJsonPath('data.documento', '20601234567');
    $this->assertDatabaseHas('compradores', ['nombre' => 'Distribuidora San Miguel SAC', 'documento' => '20601234567']);
});
