<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla principal de acopio muestra los KPIs y filtra por zona', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');
    $proveedorA = fixture_proveedor($zonaA, '11112222');
    $proveedorB = fixture_proveedor($zonaB, '33334444');
    $acopiadorA = fixture_acopiador($zonaA);
    $acopiadorB = fixture_acopiador($zonaB);
    fixture_entrega($proveedorA, $acopiadorA, 30.0);
    fixture_entrega($proveedorB, $acopiadorB, 20.0);

    $response = $this->actingAs($admin)->get(route('admin.entregas.index', ['zona_id' => $zonaA->id]));

    $response->assertOk();
    $response->assertSee('Litros registrados');
    $response->assertSee('11112222');
});

it('el detalle de una entrega PENDIENTE_ANALISIS permite editar, y el formulario actualiza los datos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);

    $get = $this->actingAs($admin)->get(route('admin.entregas.show', $entrega->id));
    $get->assertOk();
    $get->assertSee('Editar');
    $get->assertSee('Registrado por');

    $update = $this->actingAs($admin)->patch(route('admin.entregas.update', $entrega->id), [
        'litros' => 45.0,
        'motivo_modificacion' => 'Corrección de litros',
    ]);

    $update->assertRedirect(route('admin.entregas.show', $entrega->id));
    $this->assertDatabaseHas('entregas', ['id' => $entrega->id, 'litros' => 45.0]);
});

it('no se puede editar una entrega ya ANALIZADA', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    $entrega->update(['estado' => 'ANALIZADA']);

    $get = $this->actingAs($admin)->get(route('admin.entregas.show', $entrega->id));
    $get->assertOk();
    $get->assertDontSee('Editar entrega', false);

    $update = $this->actingAs($admin)->patch(route('admin.entregas.update', $entrega->id), [
        'litros' => 99.0,
        'motivo_modificacion' => 'x',
    ]);

    // Una entrega ya analizada no se edita: el error de negocio vuelve al formulario con un mensaje.
    $update->assertRedirect();
    $update->assertSessionHas('error');
});
