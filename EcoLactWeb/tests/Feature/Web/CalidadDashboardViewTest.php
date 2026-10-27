<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('el dashboard de calidad muestra los KPIs y las secciones principales', function () {
    $analista = fixture_usuario('CALIDAD');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    fixture_entrega($proveedor, $acopiador, 30.0);

    $response = $this->actingAs($analista)->get(route('calidad.dashboard'));

    $response->assertOk();
    $response->assertSee('Muestras pendientes');
    $response->assertSee('Seleccionar muestra');
    $response->assertDontSee('Pendientes de análisis');
    $response->assertSee('Últimos análisis');
    $response->assertSee('Comunicados recientes');
});
