<?php

use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\RegistrarRuta;
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

it('muestra zona, ruta, latitud, longitud y referencia con etiquetas claras y un botón para verlos en el mapa', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor] = proveedorConRuta(['latitud' => -15.842334, 'longitud' => -70.021223, 'referenciaUbicacion' => 'Frente a la escuela de Collana']);

    $response = $this->actingAs($admin)->get(route('admin.proveedores.show', $proveedor->id));

    $response->assertOk()
        ->assertSeeInOrder(['Datos personales', 'Nombres', 'Ana', 'Apellidos', 'Choque Condori', 'DNI', '72100006', 'Teléfono', '951700006', 'Fecha de registro'])
        ->assertSeeInOrder(['Ubicación y ruta de acopio', 'Zona', 'Collana 2', 'Ruta de acopio', 'Ruta principal'])
        ->assertSeeInOrder(['Ubicación del punto de acopio', 'Latitud', '-15.842334', 'Longitud', '-70.021223', 'Referencia', 'Frente a la escuela de Collana'])
        ->assertSee('Coordenadas: -15.842334, -70.021223')
        ->assertSee('Ver en mapa')
        ->assertSee('https://www.google.com/maps/search/?api=1&amp;query=-15.842334,-70.021223', false)
        ->assertSee('Editar ubicación')
        ->assertDontSee('Sin ubicación registrada');
});

it('no muestra textos técnicos como "(derivada de su ruta)" ni "Zona actual"', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor] = proveedorConRuta();

    $this->actingAs($admin)->get(route('admin.proveedores.show', $proveedor->id))
        ->assertOk()
        ->assertDontSee('derivada de su ruta')
        ->assertDontSee('Zona actual')
        ->assertDontSee('Ruta actual');
});

it('con ruta pero sin coordenadas avisa "Sin ubicación registrada", marca la referencia como no registrada y ofrece registrarla', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor, $zona] = proveedorConRuta();

    $this->actingAs($admin)->get(route('admin.proveedores.show', $proveedor->id))
        ->assertOk()
        ->assertSee('Sin ubicación registrada')
        ->assertSee('Este proveedor todavía no tiene un punto de acopio georreferenciado.')
        ->assertSee('No registrada')
        ->assertSee('Registrar ubicación')
        ->assertDontSee('Ver en mapa')
        // Reutiliza el modal de asignación de Zonas en vez de un flujo paralelo.
        ->assertSee(e(route('admin.zonas.index', ['zona' => $zona->id, 'asignar' => '72100006'])), false);
});

it('sin zona ni ruta muestra "Sin asignar" y el acceso a asignarlo desde Zonas', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $proveedor = fixture_proveedor(fixture_zona('Otra'), '72100099');
    $proveedor->update(['zona_id' => null]);

    $this->actingAs($admin)->get(route('admin.proveedores.show', $proveedor->id))
        ->assertOk()
        ->assertSee('Sin asignar')
        ->assertSee('todavía no está asignado a una zona y ruta de acopio')
        ->assertSee('Asignar a zona y ruta')
        ->assertDontSee('Ubicación del punto de acopio')
        ->assertSee(e(route('admin.zonas.index', ['zona' => 'sin-asignar', 'asignar' => '72100099'])), false);
});

it('el teléfono ausente se muestra como no registrado, en estilo secundario', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor] = proveedorConRuta();
    $proveedor->update(['telefono' => null]);

    $this->actingAs($admin)->get(route('admin.proveedores.show', $proveedor->id))
        ->assertSee('No registrado');
});

it('las etiquetas van en pequeño, gris y mayúsculas y los valores en oscuro y más grandes', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor] = proveedorConRuta();

    $this->actingAs($admin)->get(route('admin.proveedores.show', $proveedor->id))
        ->assertSee('text-[11px] font-medium uppercase tracking-wide text-text-muted', false)
        ->assertSee('text-base font-semibold text-text', false);
});

it('mantiene el QR con su token, la leyenda con el nombre y los botones Editar y Desactivar del sistema visual', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor] = proveedorConRuta();

    $this->actingAs($admin)->get(route('admin.proveedores.show', $proveedor->id))
        ->assertOk()
        ->assertSee('Código QR del proveedor')
        ->assertSee('data-qr-code="'.base64_encode($proveedor->qr_token).'"', false)
        ->assertSee('Escanea este código para identificar a')
        ->assertSee('bg-warning text-text', false)
        ->assertSee('modal-editar-proveedor', false)
        ->assertSee('modal-desactivar-proveedor', false)
        ->assertSee('Pago del proveedor')
        ->assertSee('El pago semanal se genera solo desde Configuración de pagos y se confirma en Pagos.');
});

it('un proveedor inactivo muestra Reactivar en lugar de Desactivar', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor] = proveedorConRuta();
    $proveedor->update(['estado' => 'INACTIVO']);

    $this->actingAs($admin)->get(route('admin.proveedores.show', $proveedor->id))
        ->assertSee('modal-reactivar-proveedor', false)
        ->assertDontSee('data-modal-open="modal-desactivar-proveedor"', false);
});

it('la pestaña activa se distingue con color teal, subrayado y peso mayor', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$proveedor] = proveedorConRuta();

    $this->actingAs($admin)->get(route('admin.proveedores.show', $proveedor->id))
        ->assertSee('data-[active=true]:border-primary', false)
        ->assertSee('data-[active=true]:font-semibold', false)
        ->assertSee('Resumen')->assertSee('Liquidaciones')->assertSee('Compras');
});

it('el perfil del acopiador usa la misma jerarquía etiqueta/valor y llama "Zona" a su zona', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $acopiador = fixture_acopiador($zona, '33334444');
    $acopiador->update(['nombres' => 'Luis', 'apellidos' => 'Mamani Quispe', 'telefono' => null]);

    $this->actingAs($admin)->get(route('admin.acopiadores.show', $acopiador->id))
        ->assertOk()
        ->assertSeeInOrder(['Datos personales', 'Nombres', 'Luis', 'Apellidos', 'Mamani Quispe', 'DNI', '33334444', 'Teléfono', 'No registrado'])
        ->assertSeeInOrder(['Zona de acopio e ingreso', 'Zona', 'Collana 1', 'Fecha de ingreso'])
        ->assertSee('text-[11px] font-medium uppercase tracking-wide text-text-muted', false)
        ->assertDontSee('Zona actual');
});
