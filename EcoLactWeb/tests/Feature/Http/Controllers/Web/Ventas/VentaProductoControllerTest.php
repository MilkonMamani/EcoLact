<?php

use App\Application\Ventas\RegistrarProductoPrecio;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Http\Controllers\Web\Ventas\VentaProductoController;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function vpc_crearProducto(): ProductoModel
{
    return ProductoModel::create([
        'nombre' => 'Queso Paria '.uniqid(),
        'unidad_medida' => 'UNIDAD',
        'estado' => 'ACTIVO',
    ]);
}

it('index delega en VentasListadoQuery y expone el historial, el catálogo vendible y los compradores a la vista', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = vpc_crearProducto();

    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PUBLICO', 20.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);
    app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        cantidad: 2.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
    );

    registrar_vistas_stub();
    $this->actingAs($admin);

    $view = app(VentaProductoController::class)->index(crear_request_autenticado());

    expect($view->name())->toBe('ventas.ventas.general');
    expect($view->getData()['datos']->kpis['ventas'])->toBe(1);
    expect($view->getData()['catalogo'])->toHaveCount(1);
});

it('un PROVEEDOR no puede ver el listado general de ventas', function () {
    $zona = fixture_zona();
    $proveedorUser = fixture_usuario('PROVEEDOR');

    registrar_vistas_stub();
    $this->actingAs($proveedorUser);

    app(VentaProductoController::class)->index(crear_request_autenticado());
})->throws(AuthorizationException::class);

it('la pantalla Nueva venta pinta el catálogo con presentación, stock, unidad entera y las dos formas de pago', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = vpc_crearProducto();
    $agotado = vpc_crearProducto();

    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PUBLICO', 20.0, $admin->id);
    app(RegistrarProductoPrecio::class)->execute($agotado->id, 'PUBLICO', 15.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);

    $respuesta = $this->actingAs($admin)->get(route('admin.ventas.index'))->assertOk();

    $catalogo = collect($respuesta->viewData('catalogo'))->keyBy('id');
    expect($catalogo[$producto->id])->toMatchArray(['stock' => 10.0, 'publico' => 20.0, 'entero' => true, 'presentacion' => null]);
    expect($catalogo[$agotado->id]['stock'])->toBe(0.0);

    $respuesta
        ->assertSee('El cobro se registra como ingreso en Caja.')
        ->assertSee('El importe se descontará de la liquidación correspondiente del proveedor.')
        ->assertSee('Sin stock')
        ->assertSee('data-vpos-stepper', false);
});
