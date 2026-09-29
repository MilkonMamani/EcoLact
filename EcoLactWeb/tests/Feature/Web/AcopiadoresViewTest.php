<?php

use App\Application\Acopiador\ActualizarAcopiador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla principal de acopiadores muestra el conteo de entregas y selecciona al primero', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Zona Norte');
    $acopiador = fixture_acopiador($zona, '11112222');
    $proveedor = fixture_proveedor($zona);
    fixture_entrega($proveedor, $acopiador, 30.0);

    $response = $this->actingAs($admin)->get(route('admin.acopiadores.index'));

    $response->assertOk();
    $response->assertSeeInOrder(['#', 'Nombre', 'DNI', 'Teléfono', 'Zona', 'Entregas', 'Estado']);
    $response->assertSeeInOrder(['11112222', 'Zona Norte', '1']);
    $response->assertSee('data-ficha-id="'.$acopiador->id.'"', false);
});

it('el perfil del acopiador muestra sus entregas y el historial de zona', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');
    $acopiador = fixture_acopiador($zonaA);
    $proveedor = fixture_proveedor($zonaA);
    fixture_entrega($proveedor, $acopiador, 30.0);

    app(ActualizarAcopiador::class)->execute(
        acopiadorId: $acopiador->id,
        nuevaZonaId: $zonaB->id,
        motivoCambioZona: 'Reasignación operativa',
    );

    $this->actingAs($admin)->get(route('admin.acopiadores.show', $acopiador->id))
        ->assertRedirect(route('admin.acopiadores.index', ['acopiador' => $acopiador->id]));

    $response = $this->get(route('admin.acopiadores.index', ['acopiador' => $acopiador->id]));

    $response->assertOk();
    $response->assertSeeInOrder(['Entregas registradas', '1', 'Litros acopiados', '30.00 L']);
    $response->assertSee('Reasignación operativa');
});
