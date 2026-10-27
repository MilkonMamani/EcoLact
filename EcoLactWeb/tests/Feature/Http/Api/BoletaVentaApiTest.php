<?php

use App\Application\Ventas\ConfigurarPreciosProducto;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Http\Support\BoletaVenta;
use App\Infrastructure\Persistence\Models\CompradorModel;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function escenarioBoleta(): array
{
    $admin = fixture_usuario('ADMINISTRADOR');
    $vendedor = fixture_usuario('VENTAS');
    $producto = fixture_producto('Queso Paria 1 kg');
    app(ConfigurarPreciosProducto::class)->execute($producto->id, 4.0, 5.0, 4.5, 12.0, $admin->id);
    fixture_ingreso_producto($producto->id, 120, $admin->id);

    return [$vendedor, $producto, $admin];
}

/** Hoja de la boleta tal como la imprime el PDF (el PDF comprime su texto). */
function hojaBoleta(int $ventaId): string
{
    return view('pdf.boleta-venta', ['boleta' => BoletaVenta::datos(VentaProductoModel::findOrFail($ventaId))])->render();
}

it('boleta de publico general con documento XXXXXXXX y sin datos tributarios', function () {
    [$vendedor, $producto] = escenarioBoleta();
    $venta = app(RegistrarVentaProducto::class)->execute($producto->id, 2, 'CONTADO', $vendedor->id);

    $hoja = hojaBoleta($venta->id());

    expect($hoja)->toContain('EcoLact', 'BOLETA DE VENTA', 'V-'.str_pad((string) $venta->id(), 5, '0', STR_PAD_LEFT),
        'Público general', 'XXXXXXXX', 'Queso Paria', 'S/ 5.00', 'S/ 10.00', 'Efectivo')
        ->not->toContain('IGV', 'Gravad', 'Base imponible', 'RUC 20', 'Observaci', 'SUNAT');
});

it('boleta de comprador registrado muestra razon social y RUC reales', function () {
    [$vendedor, $producto] = escenarioBoleta();
    $comprador = CompradorModel::create(['nombre' => 'Distribuidora San Miguel SAC', 'documento' => '20601234567', 'tipo_cliente' => 'PUBLICO', 'estado' => 'ACTIVO']);
    $venta = app(RegistrarVentaProducto::class)->execute($producto->id, 1, 'CONTADO', $vendedor->id, compradorId: $comprador->id);

    expect(hojaBoleta($venta->id()))->toContain('Distribuidora San Miguel SAC', 'RUC', '20601234567')->not->toContain('XXXXXXXX');
});

it('boleta de proveedor en efectivo muestra nombre completo y DNI', function () {
    [$vendedor, $producto] = escenarioBoleta();
    $proveedor = fixture_proveedor(fixture_zona(), '45678912');
    $venta = app(RegistrarVentaProducto::class)->execute($producto->id, 2, 'CONTADO', $vendedor->id, proveedorId: $proveedor->id);

    expect(hojaBoleta($venta->id()))->toContain('Proveedor Test', 'DNI', '45678912', 'S/ 4.00', 'Efectivo')
        ->not->toContain('descontará');
});

it('boleta de proveedor con descuento semanal lo indica y conserva el precio historico', function () {
    [$vendedor, $producto, $admin] = escenarioBoleta();
    $proveedor = fixture_proveedor(fixture_zona(), '45678912');
    $venta = app(RegistrarVentaProducto::class)->execute($producto->id, 2, 'CONTADO', $vendedor->id, proveedorId: $proveedor->id);
    VentaProductoModel::whereKey($venta->id())->update(['forma_pago' => 'DESCUENTO_PLANILLA']);
    app(ConfigurarPreciosProducto::class)->execute($producto->id, 9.0, 9.5, 9.0, 12.0, $admin->id);

    expect(hojaBoleta($venta->id()))->toContain('Descuento del pago semanal',
        'Este monto se descontará del próximo pago semanal del proveedor.', 'S/ 4.00', 'S/ 8.00')
        ->not->toContain('S/ 9.00');
});

it('descarga el PDF de la venta propia como application/pdf con el nombre de la boleta', function () {
    [$vendedor, $producto] = escenarioBoleta();
    $venta = app(RegistrarVentaProducto::class)->execute($producto->id, 2, 'CONTADO', $vendedor->id);
    $codigo = 'V-'.str_pad((string) $venta->id(), 5, '0', STR_PAD_LEFT);

    $respuesta = $this->actingAs($vendedor, 'sanctum')->get(route('api.ventas.ventas.boleta', $venta->id()))->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect($respuesta->headers->get('Content-Disposition'))->toContain('EcoLact_Venta_'.$codigo.'.pdf')
        ->and($respuesta->getContent())->toStartWith('%PDF');
});

it('un vendedor no puede descargar la boleta de una venta ajena', function () {
    [$vendedor, $producto] = escenarioBoleta();
    $otro = fixture_usuario('VENTAS');
    $ajena = app(RegistrarVentaProducto::class)->execute($producto->id, 1, 'CONTADO', $otro->id);

    $this->actingAs($vendedor, 'sanctum')->get(route('api.ventas.ventas.boleta', $ajena->id()))->assertForbidden();
    $this->actingAs($vendedor)->get(route('ventas.ventas.boleta', $ajena->id()))->assertForbidden();
});

it('el administrador descarga cualquier boleta por API y web', function () {
    [$vendedor, $producto, $admin] = escenarioBoleta();
    $venta = app(RegistrarVentaProducto::class)->execute($producto->id, 1, 'CONTADO', $vendedor->id);

    $this->actingAs($admin, 'sanctum')->get(route('api.admin.ventas.boleta', $venta->id()))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($admin)->get(route('admin.ventas.boleta', $venta->id()))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('una venta que no esta registrada no tiene boleta definitiva', function () {
    [$vendedor, $producto] = escenarioBoleta();
    $venta = app(RegistrarVentaProducto::class)->execute($producto->id, 1, 'CONTADO', $vendedor->id);
    VentaProductoModel::whereKey($venta->id())->update(['estado' => 'ANULADA']);

    $this->actingAs($vendedor, 'sanctum')->get(route('api.ventas.ventas.boleta', $venta->id()))->assertNotFound();
});

it('el detalle web ofrece Ver boleta con Descargar PDF', function () {
    [$vendedor, $producto] = escenarioBoleta();
    $venta = app(RegistrarVentaProducto::class)->execute($producto->id, 1, 'CONTADO', $vendedor->id);

    $this->actingAs($vendedor)->get(route('ventas.ventas.show', $venta->id()), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()->assertSee('Ver boleta')->assertSee('Descargar PDF')
        ->assertSee(route('ventas.ventas.boleta', $venta->id()), false);
});
