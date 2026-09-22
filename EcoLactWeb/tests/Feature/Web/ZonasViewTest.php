<?php

use App\Application\Ruta\RegistrarRuta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla principal de zonas muestra los KPIs y el conteo por zona', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Zona Norte');
    fixture_proveedor($zona);

    $response = $this->actingAs($admin)->get(route('admin.zonas.index'));

    $response->assertOk();
    $response->assertSee('Zona Norte');
});

it('la pantalla de zonas ya no muestra la card de rutas, solo la tabla de proveedores de la zona seleccionada', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Zona Norte');
    app(RegistrarRuta::class)->execute(zonaId: $zona->id, nombre: 'Ruta Norte - A');

    $response = $this->actingAs($admin)->get(route('admin.zonas.index'));

    $response->assertOk();
    $response->assertSee('Proveedores de la zona');
    $response->assertSee('Proveedores asignados a la zona seleccionada.');
    // "Ruta Norte - A" sigue presente en el JSON del catálogo zona→ruta que
    // necesita el modal de asignación (data-catalogo); lo que ya no debe
    // verse es la card visual de rutas ni su navegación.
    $response->assertDontSee('Recorridos y detalle de la ruta');
    $response->assertDontSee('Nueva ruta');
    $response->assertDontSee('Sin descripción registrada.');
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

it('la vista de una zona lista sus proveedores y acopiadores asignados', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Zona Norte');
    $proveedor = fixture_proveedor($zona, '11112222');
    $acopiador = fixture_acopiador($zona, '33334444');

    $response = $this->actingAs($admin)->get(route('admin.zonas.show', $zona->id));

    $response->assertOk();
    $response->assertSee('Proveedores de esta zona');
    $response->assertSee('11112222');
    $response->assertSee('33334444');
});

it('desactivar una zona con proveedores activos asignados falla', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Zona Norte');
    fixture_proveedor($zona);

    $response = $this->actingAs($admin)->patch(route('admin.zonas.update', $zona->id), [
        'estado' => 'INACTIVO',
    ]);

    $response->assertStatus(500);
});
