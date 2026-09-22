<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla de seguimiento de calidad muestra los KPIs y distingue estado de resultado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    $entregaAnalizada = fixture_entrega($proveedor, $acopiador, 20.0);
    fixture_analisis_confirmado($entregaAnalizada, 'OBSERVADA', $admin->id);
    fixture_entrega($proveedor, $acopiador, 15.0);

    $response = $this->actingAs($acopiador->user)->get(route('acopiador.seguimiento-calidad.index'));

    $response->assertOk();
    $response->assertSee('Pendientes');
    $response->assertSee('Con observaciones');
    $response->assertSee('Observada');
    $response->assertSee('Sin resultado');
});

it('el detalle del seguimiento muestra el proveedor y el resultado de calidad, sin acciones de edición', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    $entrega = fixture_entrega($proveedor, $acopiador, 20.0);
    fixture_analisis_confirmado($entrega, 'RECHAZADA', $admin->id);

    $response = $this->actingAs($acopiador->user)->get(route('acopiador.seguimiento-calidad.show', $entrega->id));

    $response->assertOk();
    $response->assertSee('Proveedor Test');
    $response->assertSee('Rechazada');
    $response->assertDontSee('Registrar resultado');
});

it('un acopiador no puede ver el seguimiento de una entrega de otra zona (403)', function () {
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');
    $proveedorB = fixture_proveedor($zonaB);
    $acopiadorA = fixture_acopiador($zonaA);
    $acopiadorB = fixture_acopiador($zonaB);

    $entregaB = fixture_entrega($proveedorB, $acopiadorB, 20.0);

    $this->actingAs($acopiadorA->user)->get(route('acopiador.seguimiento-calidad.show', $entregaB->id))->assertForbidden();
});
