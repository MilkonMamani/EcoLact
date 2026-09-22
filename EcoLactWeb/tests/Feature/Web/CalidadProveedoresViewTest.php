<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla de proveedores muestra proveedores de cualquier zona con sus KPIs', function () {
    $analista = fixture_usuario('CALIDAD');
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');
    fixture_proveedor($zonaA);
    fixture_proveedor($zonaB);

    $response = $this->actingAs($analista)->get(route('calidad.proveedores.index'));

    $response->assertOk();
    $response->assertSee('Proveedores activos');
});

it('el perfil del proveedor muestra sus entregas y su historial de calidad, sin liquidaciones ni compras', function () {
    $analista = fixture_usuario('CALIDAD');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $analista->id);
    $entrega->update(['estado' => 'ANALIZADA']);

    $response = $this->actingAs($analista)->get(route('calidad.proveedores.show', $proveedor->id));

    $response->assertOk();
    $response->assertSee('Entregas');
    $response->assertSee('Calidad');
    $response->assertDontSee('Liquidaciones');
    $response->assertDontSee('Compras');
});
