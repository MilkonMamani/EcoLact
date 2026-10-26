<?php

use App\Application\Ventas\ObtenerVentas;
use App\Application\Ventas\RegistrarProductoPrecio;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function ov_crearProducto(): ProductoModel
{
    return ProductoModel::create([
        'nombre' => 'Queso Paria '.uniqid(),
        'unidad_medida' => 'UNIDAD',
        'estado' => 'ACTIVO',
    ]);
}

it('lista todas las ventas de todos los clientes, sin acotar por proveedor', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $productoA = ov_crearProducto();
    $productoB = ov_crearProducto();

    app(RegistrarProductoPrecio::class)->execute($productoA->id, 'PUBLICO', 20.0, $admin->id);
    fixture_ingreso_producto($productoA->id, 10.0, $admin->id);
    app(RegistrarProductoPrecio::class)->execute($productoB->id, 'PUBLICO', 19.0, $admin->id);
    fixture_ingreso_producto($productoB->id, 10.0, $admin->id);

    app(RegistrarVentaProducto::class)->execute(
        productoId: $productoA->id,
        cantidad: 2.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
    );
    app(RegistrarVentaProducto::class)->execute(
        productoId: $productoB->id,
        cantidad: 3.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
    );

    $ventas = app(ObtenerVentas::class)->execute();

    expect($ventas)->toHaveCount(2);
    $productoIds = collect($ventas)->map(fn ($venta) => $venta->productoId())->all();
    expect($productoIds)->toEqualCanonicalizing([$productoA->id, $productoB->id]);
});
