<?php

use App\Application\Ventas\ConfigurarPreciosProducto;
use App\Application\Ventas\RegistrarComprador;
use App\Application\Ventas\RegistrarProductoPrecio;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Infrastructure\Persistence\Models\MovimientoCajaModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function ventasview_crearProducto(string $nombre): ProductoModel
{
    return ProductoModel::create(['nombre' => $nombre, 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
}

it('Ventas / POS muestra las pestañas Nueva venta e Historial, sin los KPIs de dashboard', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = ventasview_crearProducto('Queso Paria Fresco');
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PUBLICO', 20.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);

    $response = $this->actingAs($admin)->get(route('admin.ventas.index'));

    $response->assertOk();
    $response->assertSee('Ventas / POS');
    $response->assertSeeInOrder(['Nueva venta', 'Historial']);
    $response->assertSee('¿Quién compra?');
    $response->assertSee('Queso Paria Fresco');
    $response->assertSee('Resumen de venta');
    $response->assertDontSee('Total vendido');
    $response->assertDontSee('Productos vendidos');
    // Ni precio ni tipo de precio viajan en el formulario: los decide el backend.
    $response->assertDontSee('name="precio_unitario"', false);
    $response->assertDontSee('name="tipo_cliente"', false);
});

it('no ofrece productos sin un precio utilizable', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $conPrecio = ventasview_crearProducto('Queso Con Precio');
    app(RegistrarProductoPrecio::class)->execute($conPrecio->id, 'PUBLICO', 20.0, $admin->id);
    ventasview_crearProducto('Yogurt Sin Precio');

    $response = $this->actingAs($admin)->get(route('admin.ventas.index'));

    $response->assertSee('Queso Con Precio');
    $response->assertDontSee('Yogurt Sin Precio');
});

it('registra una venta al público general sin enviar precio ni tipo y confirma con stock y caja', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = ventasview_crearProducto('Queso Paria Fresco');
    app(ConfigurarPreciosProducto::class)->execute($producto->id, 4.0, 5.0, 4.5, 12.0, $admin->id);
    fixture_ingreso_producto($producto->id, 20.0, $admin->id);

    $post = $this->actingAs($admin)->post(route('admin.ventas.store'), [
        'cliente' => 'PUBLICO',
        'producto_id' => $producto->id,
        'cantidad' => 12,
        'forma_pago' => 'CONTADO',
        // Un navegador manipulado no puede imponer precio ni tipo.
        'precio_unitario' => 0.01,
        'tipo_cliente' => 'PROVEEDOR',
    ]);

    $post->assertRedirect(route('admin.ventas.index'));
    $venta = VentaProductoModel::sole();
    expect($venta->tipo_cliente)->toBe('MAYORISTA');
    expect((float) $venta->precio_unitario)->toBe(4.5);
    expect((float) $venta->importe_total)->toBe(54.0);
    expect($venta->proveedor_id)->toBeNull();

    $this->actingAs($admin)->get(route('admin.ventas.index'))
        ->assertSee('Venta registrada correctamente')
        ->assertSee('20 → 8')
        ->assertSee('+ S/ 54.00');
});

it('si el registro falla vuelve a Nueva venta con el motivo y lo ingresado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = ventasview_crearProducto('Queso Paria Fresco');
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PUBLICO', 5.0, $admin->id);
    fixture_ingreso_producto($producto->id, 20.0, $admin->id);

    $this->actingAs($admin)->post(route('admin.ventas.store'), [
        'cliente' => 'PUBLICO', 'producto_id' => $producto->id, 'cantidad' => 21, 'forma_pago' => 'CONTADO',
    ])->assertRedirect(route('admin.ventas.index'))->assertSessionHas('venta_error', 'No hay stock suficiente del producto solicitado.');

    expect(VentaProductoModel::count())->toBe(0);
    expect(MovimientoCajaModel::count())->toBe(0);
});

it('exige el comprador cuando quien compra es un comprador registrado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = ventasview_crearProducto('Queso Paria Fresco');

    $this->actingAs($admin)->post(route('admin.ventas.store'), [
        'cliente' => 'COMPRADOR', 'producto_id' => $producto->id, 'cantidad' => 1, 'forma_pago' => 'CONTADO',
    ])->assertSessionHasErrors('comprador_id');
});

it('el historial filtra por forma de pago y busca por código de venta', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $proveedor = fixture_proveedor(fixture_zona());
    $productoA = ventasview_crearProducto('Queso A');
    $productoB = ventasview_crearProducto('Queso B');
    app(RegistrarProductoPrecio::class)->execute($productoA->id, 'PROVEEDOR', 18.0, $admin->id);
    fixture_ingreso_producto($productoA->id, 10.0, $admin->id);
    app(RegistrarProductoPrecio::class)->execute($productoB->id, 'PUBLICO', 8.0, $admin->id);
    fixture_ingreso_producto($productoB->id, 10.0, $admin->id);

    app(RegistrarVentaProducto::class)->execute(productoId: $productoA->id, cantidad: 2.0, formaPago: 'CONTADO', registradoPorUserId: $admin->id, proveedorId: $proveedor->id);
    $ventaB = app(RegistrarVentaProducto::class)->execute(productoId: $productoB->id, cantidad: 3.0, formaPago: 'CONTADO', registradoPorUserId: $admin->id);

    $historial = $this->actingAs($admin)->get(route('admin.ventas.index', ['tab' => 'historial']));
    $historial->assertOk();
    $historial->assertSee('Historial de ventas');
    $historial->assertSee('V-'.str_pad((string) $ventaB->id(), 5, '0', STR_PAD_LEFT));
    $historial->assertSee('Público general');
    $historial->assertSee('Contado');

    $porCodigo = $this->actingAs($admin)->get(route('admin.ventas.index', ['tab' => 'historial', 'q' => 'V-'.$ventaB->id()]));
    expect($porCodigo->viewData('datos')->filas)->toHaveCount(1);
    expect($porCodigo->viewData('datos')->filas[0]['id'])->toBe($ventaB->id());
});

it('el detalle de una venta es comercial: precio aplicado guardado y sin datos técnicos de inventario', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = ventasview_crearProducto('Queso Madurado');
    app(ConfigurarPreciosProducto::class)->execute($producto->id, null, 20.0, 19.0, 4.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);
    $comprador = app(RegistrarComprador::class)->execute('Comercial X');

    $venta = app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        cantidad: 4.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
        compradorId: $comprador->id(),
    );

    // El panel lateral (fetch) recibe solo el fragmento.
    $panel = $this->actingAs($admin)->get(route('admin.ventas.show', $venta->id()), ['X-Requested-With' => 'XMLHttpRequest']);
    $panel->assertOk();
    $panel->assertSee('Comercial X');
    $panel->assertSee('Mayorista');
    $panel->assertSee('S/ 19.00');
    $panel->assertSee('S/ 76.00');
    $panel->assertDontSee('producto_movimientos');
    $panel->assertDontSee('Impacto en inventario');
    $panel->assertDontSee('Ventas / POS');

    // Enlace directo (p. ej. desde Caja): la pantalla con el Historial y el detalle abierto.
    $pagina = $this->actingAs($admin)->get(route('admin.ventas.show', $venta->id()));
    $pagina->assertOk();
    $pagina->assertSee('Ventas / POS');
    $pagina->assertSee('Venta V-'.str_pad((string) $venta->id(), 5, '0', STR_PAD_LEFT));
});
