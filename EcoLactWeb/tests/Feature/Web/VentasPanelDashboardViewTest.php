<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla dashboard de ventas muestra los KPIs y el CTA de registrar venta', function () {
    $ventas = fixture_usuario('VENTAS');

    $response = $this->actingAs($ventas)->get(route('ventas.dashboard'));

    $response->assertOk();
    $response->assertSee('Dashboard');
    $response->assertSee('Registrar venta');
    $response->assertSee('Ticket promedio');
});

it('el dashboard muestra el estado positivo cuando no hay stock crítico', function () {
    $ventas = fixture_usuario('VENTAS');

    $response = $this->actingAs($ventas)->get(route('ventas.dashboard'));

    $response->assertOk();
    $response->assertSee('No hay productos con stock crítico');
});

it('un ENCARGADO_PLANTA no puede acceder al panel de ventas (403)', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');

    $this->actingAs($encargado)->get(route('ventas.dashboard'))->assertForbidden();
});
