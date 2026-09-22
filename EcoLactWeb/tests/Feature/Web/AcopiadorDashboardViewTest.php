<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla dashboard del acopiador muestra los KPIs y el CTA de registrar entrega', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    $entrega = fixture_entrega($proveedor, $acopiador, 20.0, new DateTimeImmutable);
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $admin->id);

    $response = $this->actingAs($acopiador->user)->get(route('acopiador.dashboard'));

    $response->assertOk();
    $response->assertSee('Dashboard');
    $response->assertSee('Registrar entrega');
    $response->assertSee('Litros');
    $response->assertSee('Pendientes');
});

it('el dashboard muestra el estado "todo al día" cuando no hay pendientes de análisis', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);

    $response = $this->actingAs($acopiador->user)->get(route('acopiador.dashboard'));

    $response->assertOk();
    $response->assertSee('No tienes entregas pendientes de análisis');
});
