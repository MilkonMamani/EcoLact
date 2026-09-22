<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('el dashboard del proveedor muestra los KPIs y las secciones principales', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    fixture_entrega($proveedor, $acopiador, 25.0);

    $response = $this->actingAs($proveedor->user)->get(route('proveedor.dashboard'));

    $response->assertOk();
    $response->assertSee('Entregas realizadas');
    $response->assertSee('Mi última liquidación');
    $response->assertSee('Último análisis de calidad');
    $response->assertSee('Mis compras recientes');
    $response->assertSee('Comunicados recientes');
});
