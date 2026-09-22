<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla dashboard de planta muestra los KPIs y el CTA de registrar producción', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');

    $response = $this->actingAs($encargado)->get(route('planta.dashboard'));

    $response->assertOk();
    $response->assertSee('Dashboard');
    $response->assertSee('Registrar producción');
    $response->assertSee('Litros utilizados');
});

it('el dashboard muestra el estado positivo cuando no hay alertas de insumos', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');

    $response = $this->actingAs($encargado)->get(route('planta.dashboard'));

    $response->assertOk();
    $response->assertSee('No hay insumos que requieran atención');
});

it('un ACOPIADOR no puede acceder al panel de planta (403)', function () {
    $acopiador = fixture_acopiador(fixture_zona());

    $this->actingAs($acopiador->user)->get(route('planta.dashboard'))->assertForbidden();
});
