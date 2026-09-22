<?php

use App\Application\Ventas\RegistrarProductoPrecio;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla de mis compras muestra los KPIs y el historial', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);

    $producto = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PROVEEDOR', 25.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);

    app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        tipoCliente: 'PROVEEDOR',
        cantidad: 2.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
        proveedorId: $proveedor->id,
    );

    $response = $this->actingAs($proveedor->user)->get(route('proveedor.compras.index'));

    $response->assertOk();
    $response->assertSee('Total comprado');
    $response->assertSee('Queso Paria');
});

it('el detalle de una compra muestra el producto y el importe', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);

    $producto = ProductoModel::create(['nombre' => 'Yogur', 'unidad_medida' => 'LITRO', 'estado' => 'ACTIVO']);
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PROVEEDOR', 12.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);

    $venta = app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        tipoCliente: 'PROVEEDOR',
        cantidad: 3.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
        proveedorId: $proveedor->id,
    );

    $response = $this->actingAs($proveedor->user)->get(route('proveedor.compras.show', $venta->id()));

    $response->assertOk();
    $response->assertSee('Yogur');
    $response->assertSee('S/ 36.00');
});

it('un proveedor no puede ver la compra de otro proveedor (403)', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedorA = fixture_proveedor($zona);
    $proveedorB = fixture_proveedor($zona);

    $producto = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PROVEEDOR', 25.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);

    $venta = app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        tipoCliente: 'PROVEEDOR',
        cantidad: 1.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
        proveedorId: $proveedorA->id,
    );

    $this->actingAs($proveedorB->user)->get(route('proveedor.compras.show', $venta->id()))->assertForbidden();
});
