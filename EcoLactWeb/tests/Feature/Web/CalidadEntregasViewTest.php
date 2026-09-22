<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla de entregas muestra entregas de cualquier zona con su resultado de calidad', function () {
    $analista = fixture_usuario('CALIDAD');
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');
    $proveedorA = fixture_proveedor($zonaA);
    $proveedorB = fixture_proveedor($zonaB);
    $acopiadorA = fixture_acopiador($zonaA);
    $acopiadorB = fixture_acopiador($zonaB);

    $entregaA = fixture_entrega($proveedorA, $acopiadorA, 20.0);
    $entregaB = fixture_entrega($proveedorB, $acopiadorB, 25.0);
    fixture_analisis_confirmado($entregaB, 'OBSERVADA', $analista->id);
    $entregaB->update(['estado' => 'ANALIZADA']);

    $response = $this->actingAs($analista)->get(route('calidad.entregas.index'));

    $response->assertOk();
    $response->assertSee('Litros registrados');
    $response->assertSee($proveedorA->nombres);
    $response->assertSee($proveedorB->nombres);
});

it('el detalle de una entrega analizada ofrece ver el análisis', function () {
    $analista = fixture_usuario('CALIDAD');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $analista->id);
    $entrega->update(['estado' => 'ANALIZADA']);

    $response = $this->actingAs($analista)->get(route('calidad.entregas.show', $entrega->id));

    $response->assertOk();
    $response->assertSee('Ver análisis');
});
