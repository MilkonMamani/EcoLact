<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla de mis entregas muestra los KPIs y el resultado de calidad por fila', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    $entregaAnalizada = fixture_entrega($proveedor, $acopiador, 20.0);
    fixture_analisis_confirmado($entregaAnalizada, 'OBSERVADA', $admin->id);
    $entregaAnalizada->update(['estado' => 'ANALIZADA']);
    fixture_entrega($proveedor, $acopiador, 15.0);

    $response = $this->actingAs($proveedor->user)->get(route('proveedor.entregas.index'));

    $response->assertOk();
    $response->assertSee('Analizadas');
    $response->assertSee('Pendientes');
    $response->assertSee('Observada');
});

it('filtra mis entregas por estado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    $entregaAnalizada = fixture_entrega($proveedor, $acopiador, 20.0);
    fixture_analisis_confirmado($entregaAnalizada, 'ACEPTADA', $admin->id);
    $entregaAnalizada->update(['estado' => 'ANALIZADA']);
    $entregaPendiente = fixture_entrega($proveedor, $acopiador, 15.0);

    $response = $this->actingAs($proveedor->user)->get(route('proveedor.entregas.index', ['estado' => 'PENDIENTE_ANALISIS']));

    $response->assertOk();
    $response->assertSee(number_format(15.0, 2));
    $response->assertDontSee(number_format(20.0, 2));
});

it('el detalle de una entrega rechazada muestra la advertencia y los litros pagables en cero', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    fixture_analisis_confirmado($entrega, 'RECHAZADA', $admin->id);
    $entrega->update(['estado' => 'ANALIZADA']);

    $response = $this->actingAs($proveedor->user)->get(route('proveedor.entregas.show', $entrega->id));

    $response->assertOk();
    $response->assertSee('rechazada por Calidad');
    $response->assertSee('0.00 L');
});
