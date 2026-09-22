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

it('index delega en VentasListadoQuery y expone el listado general, productos y compradores a la vista', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = vpc_crearProducto();

    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PUBLICO', 20.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);
    app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        tipoCliente: 'PUBLICO',
        cantidad: 2.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
    );

    registrar_vistas_stub();
    $this->actingAs($admin);

    $view = app(VentaProductoController::class)->index(crear_request_autenticado());

    expect($view->name())->toBe('ventas.ventas.general');
    expect($view->getData()['datos']->kpis['ventas'])->toBe(1);
    expect($view->getData()['productos'])->toHaveCount(1);
});

it('un PROVEEDOR no puede ver el listado general de ventas', function () {
    $zona = fixture_zona();
    $proveedorUser = fixture_usuario('PROVEEDOR');

    registrar_vistas_stub();
    $this->actingAs($proveedorUser);

    app(VentaProductoController::class)->index(crear_request_autenticado());
})->throws(AuthorizationException::class);
