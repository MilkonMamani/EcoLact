<?php

use App\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla de stock de productos muestra los KPIs y el listado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $producto = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    fixture_ingreso_producto($producto->id, 40.0, $admin->id);

    $response = $this->actingAs($encargado)->get(route('planta.productos.index'));

    $response->assertOk();
    $response->assertSee('Queso Paria');
    $response->assertSee('Disponible');
});

it('el detalle del producto muestra el historial de movimientos y no permite editar stock', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $producto = ProductoModel::create(['nombre' => 'Yogur', 'unidad_medida' => 'LITRO', 'estado' => 'ACTIVO']);
    fixture_ingreso_producto($producto->id, 20.0, $admin->id);

    $response = $this->actingAs($encargado)->get(route('planta.productos.show', $producto->id));

    $response->assertOk();
    $response->assertSee('Yogur');
    $response->assertSee('Producción');
    $response->assertDontSee('Editar producto');
});

it('un VENTAS no puede acceder al stock de productos de planta (403)', function () {
    $ventas = fixture_usuario('VENTAS');

    $this->actingAs($ventas)->get(route('planta.productos.index'))->assertForbidden();
});
