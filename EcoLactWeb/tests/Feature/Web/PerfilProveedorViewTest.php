<?php

use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\RegistrarRuta;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function proveedorConRuta(array $ubicacion = []): array
{
    $zona = fixture_zona('Collana 2');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta principal');
    $proveedor = fixture_proveedor($zona, '72100006');
    $proveedor->update(['nombres' => 'Ana', 'apellidos' => 'Choque Condori', 'telefono' => '951700006']);
    app(AsignarProveedorARuta::class)->execute(...['rutaId' => $ruta->id(), 'proveedorId' => $proveedor->id, ...$ubicacion]);

    return [$proveedor, $zona, $ruta];
}

function venta_proveedor(int $proveedorId, int $userId, string $formaPago, float $importe, string $estado = 'REGISTRADA'): VentaProductoModel
{
    return VentaProductoModel::create([
        'producto_id' => fixture_producto("Queso {$formaPago} {$estado} {$importe}")->id,
        'proveedor_id' => $proveedorId,
        'tipo_cliente' => 'PROVEEDOR',
        'cantidad' => 2,
        'precio_unitario' => $importe / 2,
        'importe_total' => $importe,
        'forma_pago' => $formaPago,
        'estado' => $estado,
        'fecha' => now(),
        'registrado_por_user_id' => $userId,
    ]);
}

it('la antigua página de detalle redirige al listado con el proveedor seleccionado en el panel', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor] = proveedorConRuta();

    $this->actingAs($admin)->get(route('admin.proveedores.show', $proveedor->id))
        ->assertRedirect(route('admin.proveedores.index', ['proveedor' => $proveedor->id]));
});

it('el panel muestra solo las pestañas Resumen, Entregas, Calidad y Compras, con el QR únicamente en Resumen', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor] = proveedorConRuta();

    $html = $this->actingAs($admin)->get(route('admin.proveedores.panel', $proveedor->id))
        ->assertOk()
        ->assertSeeInOrder(['Resumen', 'Entregas', 'Calidad', 'Compras'])
        ->assertDontSee('data-fichas-tab="zona"', false)
        ->getContent();

    expect(substr_count($html, 'data-qr-code="'.base64_encode($proveedor->qr_token).'"'))->toBe(1);

    $posicionQr = strpos($html, 'data-qr-code=');
    expect($posicionQr)
        ->toBeGreaterThan(strpos($html, 'data-fichas-tab-panel="resumen"'))
        ->toBeLessThan(strpos($html, 'data-fichas-tab-panel="entregas"'));
});

it('Resumen muestra información personal y ubicación y zona, cada una con su único botón Editar', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor, $zona] = proveedorConRuta(['latitud' => -15.842334, 'longitud' => -70.021223, 'referenciaUbicacion' => 'Frente a la escuela de Collana']);

    $html = $this->actingAs($admin)->get(route('admin.proveedores.panel', $proveedor->id))
        ->assertOk()
        ->assertSeeInOrder(['Información personal', 'Nombres', 'Ana', 'Apellidos', 'Choque Condori', 'Documento (DNI)', '72100006', 'Teléfono', '951700006', 'Estado', 'Activo', 'Fecha de registro'])
        ->assertSeeInOrder(['Ubicación y zona', 'Zona asignada', 'Collana 2', 'Ruta de acopio', 'Ruta principal', 'Referencia', 'Frente a la escuela de Collana', 'Latitud', '-15.842334', 'Longitud', '-70.021223'])
        ->assertSee('https://www.google.com/maps/search/?api=1&amp;query=-15.842334,-70.021223', false)
        ->assertSee(e(route('admin.zonas.index', ['zona' => $zona->id, 'asignar' => '72100006'])), false)
        ->getContent();

    expect(substr_count($html, 'class="fichas-editar"'))->toBe(2);
});

it('sin zona ni ruta muestra "Sin asignar" y el acceso para asignarlo desde Zonas', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $proveedor = fixture_proveedor(fixture_zona('Otra'), '72100099');
    $proveedor->update(['zona_id' => null, 'telefono' => null]);

    $this->actingAs($admin)->get(route('admin.proveedores.panel', $proveedor->id))
        ->assertOk()
        ->assertSee('Sin asignar')
        ->assertSee('No registrado')
        ->assertSee('Todavía no está asignado a una zona y ruta de acopio.')
        ->assertDontSee('Ver en mapa')
        ->assertSee(e(route('admin.zonas.index', ['zona' => 'sin-asignar', 'asignar' => '72100099'])), false);
});

it('el panel ya no activa ni desactiva: esa acción vive solo en el switch del listado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor] = proveedorConRuta();
    $proveedor->update(['estado' => 'INACTIVO']);

    $this->actingAs($admin)->get(route('admin.proveedores.panel', $proveedor->id))
        ->assertOk()
        ->assertSee('Inactivo')
        ->assertDontSee(route('admin.proveedores.reactivar', $proveedor->id), false)
        ->assertDontSee(route('admin.proveedores.desactivar', $proveedor->id), false);
});

it('Entregas lista las entregas del proveedor con la zona donde se acopió cada una', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor, $zona] = proveedorConRuta();
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 42.5);
    $antigua = fixture_entrega($proveedor, $acopiador, 10.0, new DateTimeImmutable('-10 days'));
    $antigua->update(['zona_id' => fixture_zona('Collana Antigua')->id]);

    $this->actingAs($admin)->get(route('admin.proveedores.panel', $proveedor->id))
        ->assertSeeInOrder(['Fecha', 'Zona', 'Litros', 'Identificación', 'Estado', 'Ver'])
        ->assertSeeInOrder(['Collana 2', '42.50', 'DNI', 'Pendiente análisis', 'Collana Antigua', '10.00'])
        ->assertSee(route('admin.entregas.show', $entrega->id), false);
});

it('Compras muestra solo lo cargado al pago semanal, como Descontado, con el total descontado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor] = proveedorConRuta();
    venta_proveedor($proveedor->id, $admin->id, 'DESCUENTO_PLANILLA', 36.0);
    venta_proveedor($proveedor->id, $admin->id, 'DESCUENTO_PLANILLA', 14.0);
    venta_proveedor($proveedor->id, $admin->id, 'CONTADO', 99.0);
    venta_proveedor($proveedor->id, $admin->id, 'DESCUENTO_PLANILLA', 77.0, 'ANULADA');

    $html = $this->actingAs($admin)->get(route('admin.proveedores.panel', $proveedor->id))
        ->assertSee('Queso DESCUENTO_PLANILLA REGISTRADA 36')
        ->assertSee('Queso DESCUENTO_PLANILLA REGISTRADA 14')
        ->assertDontSee('Queso CONTADO')
        ->assertDontSee('ANULADA')
        ->assertDontSee('Pendiente de descuento')
        ->assertSeeInOrder(['Total descontado', 'S/ 50.00', '2 compras a cuenta de su pago semanal'])
        ->getContent();

    // Un estado compacto "Descontado" (doble check verde con tooltip) por cada una de las dos compras.
    expect(substr_count($html, 'class="estado-compacto is-positivo" data-tooltip="Descontado"'))->toBe(2);
});

it('el panel de un proveedor exige sesión de administrador', function () {
    [$proveedor] = proveedorConRuta();
    $otroProveedor = fixture_proveedor(fixture_zona('Otra'));

    $this->get(route('admin.proveedores.panel', $proveedor->id))->assertRedirect(route('login'));
    $this->actingAs($otroProveedor->user)->get(route('admin.proveedores.panel', $proveedor->id))->assertForbidden();
});

it('el perfil del acopiador usa la misma jerarquía etiqueta/valor que Proveedores', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $acopiador = fixture_acopiador($zona, '33334444');
    $acopiador->update(['nombres' => 'Luis', 'apellidos' => 'Mamani Quispe', 'telefono' => null]);

    $this->actingAs($admin)->get(route('admin.acopiadores.panel', $acopiador->id))
        ->assertOk()
        ->assertSeeInOrder(['Información personal', 'Nombres', 'Luis', 'Apellidos', 'Mamani Quispe', 'Documento (DNI)', '33334444', 'Teléfono', 'No registrado'])
        ->assertSeeInOrder(['Fecha de ingreso', 'Estado', 'Activo', 'Zona actual', 'Collana 1'])
        ->assertSee('<dl class="fichas-datos">', false);
});
