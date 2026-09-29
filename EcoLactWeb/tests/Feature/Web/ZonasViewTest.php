<?php

use App\Application\Ruta\AsignarProveedorARuta;
use App\Application\Ruta\RegistrarRuta;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\RutaModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/*
 * Zonas usa la pantalla "listado + panel" de Proveedores y Acopiadores: tabla de zonas a la
 * izquierda y panel de la seleccionada (Resumen / Proveedores / Acopiadores) a la derecha.
 */

function zonas_asignar(ZonaModel $zona, ?float $lat = null, ?float $lng = null): ProveedorModel
{
    $ruta = RutaModel::where('zona_id', $zona->id)->first()
        ?? RutaModel::find(app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta '.$zona->nombre)->id());
    $proveedor = fixture_proveedor($zona);
    app(AsignarProveedorARuta::class)->execute(rutaId: $ruta->id, proveedorId: $proveedor->id, latitud: $lat, longitud: $lng);

    return $proveedor;
}

beforeEach(function () {
    // Martes 29/09/2026 10:00 en Lima: la semana EcoLact va del jueves 24/09 al miércoles 30/09.
    Carbon::setTestNow(Carbon::parse('2026-09-29 10:00:00', 'America/Lima'));
});

afterEach(fn () => Carbon::setTestNow());

it('la tabla de zonas muestra proveedores, acopiadores, litros de la semana y última entrega, sin columna #', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $zona->update(['descripcion' => 'Zona principal de acopio']);
    $acopiador = fixture_acopiador($zona);
    $proveedor = zonas_asignar($zona);
    zonas_asignar($zona);

    fixture_entrega($proveedor, $acopiador, 200, new DateTimeImmutable('2026-09-25 12:00:00'));
    fixture_entrega($proveedor, $acopiador, 148, new DateTimeImmutable('2026-09-29 11:42:00'));
    fixture_entrega($proveedor, $acopiador, 500, new DateTimeImmutable('2026-09-20 12:00:00'));
    EntregaModel::latest('id')->first()->update(['estado' => 'ANULADA', 'fecha_hora' => '2026-09-29 12:00:00']);

    $this->actingAs($admin)->get(route('admin.zonas.index'))
        ->assertOk()
        ->assertSee('data-fichas-page', false)
        ->assertSeeInOrder(['Nombre', 'Descripción', 'Proveedores', 'Acopiadores', 'Litros (semana)', 'Última entrega', 'Estado'])
        ->assertSeeInOrder(['Collana 1', 'Zona principal de acopio', '2', '1', '348 L', 'Hoy 06:42'])
        ->assertSee('data-estado-switch', false)
        ->assertDontSee('<th scope="col">#</th>', false);
});

it('busca zonas por nombre o descripción, filtra por estado y pagina en el servidor', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_zona('Collana 1')->update(['descripcion' => 'Distrito norte']);
    fixture_zona('Faon')->update(['estado' => 'INACTIVO']);
    foreach (range(1, 12) as $i) {
        fixture_zona(sprintf('Zeta %02d', $i));
    }

    $this->actingAs($admin);

    $this->get(route('admin.zonas.index', ['q' => 'norte']))->assertSee('>Collana 1</a>', false)->assertDontSee('>Faon</a>', false);
    $this->get(route('admin.zonas.index', ['estado' => 'INACTIVO']))->assertSee('>Faon</a>', false)->assertDontSee('>Collana 1</a>', false);
    $this->get(route('admin.zonas.index'))->assertSee('Mostrando 1 a 12 de 14 zonas');
});

it('el Resumen muestra información, métricas, rutas y el mapa; sin pestaña Rutas', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Zona Norte');
    $zona->update(['descripcion' => 'Zona de la parte alta']);
    zonas_asignar($zona, -15.8, -70.0);
    zonas_asignar($zona);
    fixture_acopiador($zona);

    $this->actingAs($admin)->get(route('admin.zonas.panel', $zona->id))
        ->assertOk()
        ->assertSeeInOrder(['Resumen', 'Proveedores', 'Acopiadores'])
        ->assertDontSee('ficha-tab-rutas', false)
        ->assertSeeInOrder(['Información de la zona', 'Nombre', 'Zona Norte', 'Estado', 'Activa', 'Descripción', 'Zona de la parte alta', 'Creada el'])
        ->assertSeeInOrder(['Proveedores', '2', 'Acopiadores', '1', 'Litros (semana)', '0 L'])
        ->assertSeeInOrder(['Rutas', '1 ruta activa', 'Ruta Zona Norte', 'Gestionar ruta'])
        ->assertSeeInOrder(['Ubicación de proveedores', 'Ver mapa completo', 'Con coordenadas', '1', 'Sin coordenadas', '1'])
        ->assertSee('data-zona-mapa-datos', false);
});

it('la pestaña Acopiadores muestra solo los de la zona con lo entregado esta semana en ella', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $otra = fixture_zona('Faon');
    $vanessa = fixture_acopiador($zona, '77766655');
    $vanessa->update(['nombres' => 'Vanessa', 'apellidos' => 'Huanca', 'telefono' => '987456321']);
    $ajeno = fixture_acopiador($otra, '11122233');
    $proveedor = zonas_asignar($zona);
    fixture_entrega($proveedor, $vanessa, 400, new DateTimeImmutable('2026-09-26 12:00:00'));
    fixture_entrega($proveedor, $vanessa, 280, new DateTimeImmutable('2026-09-28 12:00:00'));
    fixture_entrega($proveedor, $vanessa, 90, new DateTimeImmutable('2026-09-10 12:00:00'));

    $this->actingAs($admin)->get(route('admin.zonas.panel', $zona->id))
        ->assertSeeInOrder(['Acopiador', 'DNI', 'Teléfono', 'Estado', 'Entregas (semana)', 'Litros (semana)'])
        ->assertSeeInOrder(['Vanessa Huanca', '77766655', '987456321', 'Activo', '2', '680 L'])
        ->assertSee(e(route('admin.acopiadores.index', ['acopiador' => $vanessa->id])), false)
        ->assertDontSee('11122233');
});

it('la pestaña Proveedores enlaza al perfil del proveedor y no repite la columna Zona', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Collana 1');
    $proveedor = zonas_asignar($zona, -15.8, -70.0);
    $proveedor->update(['telefono' => '951000111']);

    $this->actingAs($admin)->get(route('admin.zonas.panel', $zona->id))
        ->assertSeeInOrder(['Proveedor', 'DNI', 'Teléfono', 'Última entrega', 'Litros (semana)', 'Ubicación'])
        ->assertSeeInOrder([$proveedor->dni, '951000111', 'Sin entregas', '0 L', 'Registrada'])
        ->assertSee(e(route('admin.proveedores.index', ['proveedor' => $proveedor->id])), false)
        ->assertSee('Asignar proveedor');
});

it('show ya no es otra pantalla: redirige al listado con la zona seleccionada', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Zona Norte');

    $this->actingAs($admin)->get(route('admin.zonas.show', $zona->id))
        ->assertRedirect(route('admin.zonas.index', ['zona' => $zona->id]));
});

it('el panel de una ruta se puede cargar por separado para el acordeón', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Zona Norte');
    $ruta = app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta Norte - A');
    fixture_proveedor($zona);

    $response = $this->actingAs($admin)->get(route('admin.rutas.panel', $ruta->id()));

    $response->assertOk();
    $response->assertSee('Ruta Norte - A');
    $response->assertSee('Recorridos');
    $response->assertDontSee('<html', false);
});

it('el switch activa y desactiva por JSON y no desactiva una zona con proveedores activos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $vacia = fixture_zona('Zona vacía');
    $ocupada = fixture_zona('Zona Norte');
    fixture_proveedor($ocupada);

    $this->actingAs($admin)->patchJson(route('admin.zonas.update', $vacia->id), ['estado' => 'INACTIVO'])
        ->assertOk()
        ->assertJsonPath('zona', ['id' => $vacia->id, 'estado' => 'INACTIVO']);
    $this->patchJson(route('admin.zonas.update', $vacia->id), ['estado' => 'ACTIVO'])
        ->assertOk()
        ->assertJsonPath('zona.estado', 'ACTIVO');

    $this->patchJson(route('admin.zonas.update', $ocupada->id), ['estado' => 'INACTIVO'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'No se puede desactivar la zona: todavía tiene acopiadores o proveedores activos asignados.');
    $this->patch(route('admin.zonas.update', $ocupada->id), ['estado' => 'INACTIVO'])
        ->assertRedirect(route('admin.zonas.index', ['zona' => $ocupada->id]))
        ->assertSessionHas('error');
    expect($ocupada->fresh()->estado)->toBe('ACTIVO');
});

it('una zona nueva nace siempre activa aunque se envíe otro estado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->post(route('admin.zonas.store'), ['nombre' => 'Zona Sur', 'estado' => 'INACTIVO']);

    $this->assertDatabaseHas('zonas', ['nombre' => 'Zona Sur', 'estado' => 'ACTIVO']);
    $this->get(route('admin.zonas.index'))->assertDontSee('id="modal-nueva-zona-estado"', false);
});
