<?php

use App\Application\Ventas\RegistrarProductoPrecio;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla de ventas muestra los KPIs y el historial', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventasUser = fixture_usuario('VENTAS');
    $producto = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PUBLICO', 20.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);
    app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        tipoCliente: 'PUBLICO',
        cantidad: 2.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $ventasUser->id,
    );

    $response = $this->actingAs($ventasUser)->get(route('ventas.ventas.index'));

    $response->assertOk();
    $response->assertSee('Historial de ventas');
    $response->assertSee('Queso Paria');
});

it('la pantalla de registrar venta muestra el stock disponible por producto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventasUser = fixture_usuario('VENTAS');
    $producto = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PUBLICO', 20.0, $admin->id);
    fixture_ingreso_producto($producto->id, 15.0, $admin->id);

    $response = $this->actingAs($ventasUser)->get(route('ventas.ventas.create'));

    $response->assertOk();
    $response->assertSee('Registrar venta');
    $response->assertSee('15.00 UNIDAD disponibles');
});

it('un usuario de ventas puede registrar una venta al público y ver su detalle', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventasUser = fixture_usuario('VENTAS');
    $producto = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PUBLICO', 20.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);

    $response = $this->actingAs($ventasUser)->post(route('ventas.ventas.store'), [
        'tipo_cliente' => 'PUBLICO',
        'producto_id' => $producto->id,
        'cantidad' => 3,
        'forma_pago' => 'CONTADO',
    ]);

    $venta = VentaProductoModel::firstOrFail();
    $response->assertRedirect(route('ventas.ventas.show', $venta->id));

    $detalle = $this->actingAs($ventasUser)->get(route('ventas.ventas.show', $venta->id));
    $detalle->assertOk();
    $detalle->assertSee('Queso Paria');
    $detalle->assertSee('S/ 60.00');
});

it('un PROVEEDOR no puede ver el listado general de ventas de VENTAS (403)', function () {
    $proveedor = fixture_proveedor(fixture_zona());

    $this->actingAs($proveedor->user)->get(route('ventas.ventas.index'))->assertForbidden();
});
