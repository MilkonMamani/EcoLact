<?php

use App\Application\Ventas\RegistrarProductoPrecio;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla de stock de productos muestra los KPIs y el listado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventasUser = fixture_usuario('VENTAS');
    $producto = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    fixture_ingreso_producto($producto->id, 40.0, $admin->id);

    $response = $this->actingAs($ventasUser)->get(route('ventas.productos.index'));

    $response->assertOk();
    $response->assertSee('Queso Paria');
    $response->assertSee('Disponible');
});

it('ventas ya no registra ingresos manuales: el producto entra al inventario al finalizar la producción', function () {
    expect(Route::has('ventas.productos.ingreso'))->toBeFalse();
});

it('la pantalla de movimientos muestra entradas de producción y salidas de venta, con enlace a la venta', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventasUser = fixture_usuario('VENTAS');
    $producto = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PUBLICO', 20.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);
    $venta = app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        cantidad: 2.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $ventasUser->id,
    );

    $response = $this->actingAs($ventasUser)->get(route('ventas.productos.movimientos'));

    $response->assertOk();
    $response->assertSee('Producción');
    $response->assertSee('Venta');
    $response->assertSee(route('ventas.ventas.show', $venta->id()), false);
});

it('un ENCARGADO_PLANTA no puede acceder al stock de productos de ventas (403)', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');

    $this->actingAs($encargado)->get(route('ventas.productos.index'))->assertForbidden();
});
