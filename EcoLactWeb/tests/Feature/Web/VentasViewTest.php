<?php

use App\Application\Ventas\RegistrarComprador;
use App\Application\Ventas\RegistrarProductoPrecio;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function ventasview_crearProducto(string $nombre): ProductoModel
{
    return ProductoModel::create(['nombre' => $nombre, 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
}

it('la pantalla principal de ventas muestra los KPIs y permite registrar una venta al público', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = ventasview_crearProducto('Queso Paria Fresco');
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PUBLICO', 20.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);

    $get = $this->actingAs($admin)->get(route('admin.ventas.index'));
    $get->assertOk();
    $get->assertSee('Ventas');
    $get->assertSee('Queso Paria Fresco');

    $post = $this->actingAs($admin)->post(route('admin.ventas.store'), [
        'tipo_cliente' => 'PUBLICO',
        'producto_id' => $producto->id,
        'cantidad' => 2,
        'forma_pago' => 'CONTADO',
    ]);

    $post->assertRedirect();
    $this->assertDatabaseHas('ventas_producto', ['producto_id' => $producto->id, 'cantidad' => 2]);
});

it('la pantalla principal filtra por tipo de cliente', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $productoA = ventasview_crearProducto('Queso A');
    $productoB = ventasview_crearProducto('Queso B');

    app(RegistrarProductoPrecio::class)->execute($productoA->id, 'PROVEEDOR', 18.0, $admin->id);
    fixture_ingreso_producto($productoA->id, 10.0, $admin->id);
    app(RegistrarProductoPrecio::class)->execute($productoB->id, 'PUBLICO', 8.0, $admin->id);
    fixture_ingreso_producto($productoB->id, 10.0, $admin->id);

    app(RegistrarVentaProducto::class)->execute(
        productoId: $productoA->id,
        tipoCliente: 'PROVEEDOR',
        cantidad: 2.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
        proveedorId: $proveedor->id,
    );
    app(RegistrarVentaProducto::class)->execute(
        productoId: $productoB->id,
        tipoCliente: 'PUBLICO',
        cantidad: 3.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
    );

    $response = $this->actingAs($admin)->get(route('admin.ventas.index', ['tipo_cliente' => 'PROVEEDOR']));

    // No se afirma la ausencia de "Queso B" en la página: sigue apareciendo
    // en el <select> de "Nueva venta", que lista todos los productos
    // activos y no solo los de la tabla filtrada. El filtrado en sí ya está
    // cubierto exhaustivamente en EloquentVentasListadoQueryTest.
    $response->assertOk();
    $response->assertSee('Queso A');
});

it('el detalle de una venta muestra la información, el cliente y el impacto en inventario', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = ventasview_crearProducto('Queso Madurado');
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'MAYORISTA', 19.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);
    $comprador = app(RegistrarComprador::class)->execute('Comercial X', 'MAYORISTA');

    $venta = app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        tipoCliente: 'MAYORISTA',
        cantidad: 4.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
        compradorId: $comprador->id(),
    );

    $response = $this->actingAs($admin)->get(route('admin.ventas.show', $venta->id()));

    $response->assertOk();
    $response->assertSee('Queso Madurado');
    $response->assertSee('Comercial X');
    $response->assertSee('S/ 76.00');
    $response->assertSee('Stock actual');
});
