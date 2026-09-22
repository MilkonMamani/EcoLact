<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla de entregas muestra los KPIs, el historial y el proveedor', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    $entrega = fixture_entrega($proveedor, $acopiador, 20.0);
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $admin->id);

    $response = $this->actingAs($acopiador->user)->get(route('acopiador.entregas.index'));

    $response->assertOk();
    $response->assertSee('Pendientes');
    $response->assertSee('Proveedor Test');
    $response->assertSee('ACEPTADA');
});

it('la pantalla de registrar entrega existe como una pantalla separada del historial', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);

    $response = $this->actingAs($acopiador->user)->get(route('acopiador.entregas.create'));

    $response->assertOk();
    $response->assertSee('Registrar entrega');
});

it('el detalle de una entrega muestra la información del proveedor y el resultado de calidad', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    $entrega = fixture_entrega($proveedor, $acopiador, 20.0);
    fixture_analisis_confirmado($entrega, 'OBSERVADA', $admin->id);

    $response = $this->actingAs($acopiador->user)->get(route('acopiador.entregas.show', $entrega->id));

    $response->assertOk();
    $response->assertSee('Proveedor Test');
    $response->assertSee('Observada');
    $response->assertSee('Ver seguimiento de calidad');
});

it('un acopiador no puede ver una entrega de otra zona (403)', function () {
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');
    $proveedorB = fixture_proveedor($zonaB);
    $acopiadorA = fixture_acopiador($zonaA);
    $acopiadorB = fixture_acopiador($zonaB);

    $entregaB = fixture_entrega($proveedorB, $acopiadorB, 20.0);

    $this->actingAs($acopiadorA->user)->get(route('acopiador.entregas.show', $entregaB->id))->assertForbidden();
});
