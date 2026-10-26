<?php

use App\Application\Ventas\RegistrarProductoPrecio;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('PERSONAL DE VENTAS ve la misma pantalla Ventas / POS con su historial', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventasUser = fixture_usuario('VENTAS');
    $producto = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PUBLICO', 20.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);
    app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        cantidad: 2.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $ventasUser->id,
    );

    $response = $this->actingAs($ventasUser)->get(route('ventas.ventas.index', ['tab' => 'historial']));

    $response->assertOk();
    $response->assertSee('Ventas / POS');
    $response->assertSee('Historial de ventas');
    $response->assertSee('Queso Paria');
    $response->assertDontSee('Total vendido');
});

it('la ruta antigua de registrar venta abre la pestaña Nueva venta', function () {
    $ventasUser = fixture_usuario('VENTAS');

    $this->actingAs($ventasUser)->get(route('ventas.ventas.create'))->assertRedirect(route('ventas.ventas.index'));
});

it('un usuario de ventas registra una venta al público y ve su detalle', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventasUser = fixture_usuario('VENTAS');
    $producto = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PUBLICO', 20.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);

    $response = $this->actingAs($ventasUser)->post(route('ventas.ventas.store'), [
        'cliente' => 'PUBLICO',
        'producto_id' => $producto->id,
        'cantidad' => 3,
        'forma_pago' => 'CONTADO',
    ]);

    $venta = VentaProductoModel::firstOrFail();
    $response->assertRedirect(route('ventas.ventas.index'));
    $response->assertSessionHas('venta_registrada');

    $detalle = $this->actingAs($ventasUser)->get(route('ventas.ventas.show', $venta->id), ['X-Requested-With' => 'XMLHttpRequest']);
    $detalle->assertOk();
    $detalle->assertSee('Queso Paria');
    $detalle->assertSee('S/ 60.00');
    $detalle->assertSee('Público');
});

it('la app móvil que aún envía tipo_cliente sigue registrando, pero el precio lo decide el backend', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventasUser = fixture_usuario('VENTAS');
    $producto = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PUBLICO', 20.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);

    $this->actingAs($ventasUser)->post(route('ventas.ventas.store'), [
        'tipo_cliente' => 'MAYORISTA', 'producto_id' => $producto->id, 'cantidad' => 2, 'forma_pago' => 'CONTADO',
    ])->assertRedirect(route('ventas.ventas.index'));

    expect(VentaProductoModel::sole()->tipo_cliente)->toBe('PUBLICO');
});

it('un PROVEEDOR no puede ver el listado general de ventas de VENTAS (403)', function () {
    $proveedor = fixture_proveedor(fixture_zona());

    $this->actingAs($proveedor->user)->get(route('ventas.ventas.index'))->assertForbidden();
});
