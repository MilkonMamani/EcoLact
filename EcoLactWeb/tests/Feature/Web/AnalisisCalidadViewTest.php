<?php

use App\Application\Calidad\RegistrarAnalisisCalidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('un análisis CONFIRMADO no muestra el botón de confirmar/editar', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    $analisis = fixture_analisis_confirmado($entrega, 'ACEPTADA', $admin->id);

    $response = $this->actingAs($admin)->get(route('admin.analisis-calidad.show', $analisis->id));

    $response->assertOk();
    $response->assertSee('Análisis confirmado. No puede modificarse.');
    $response->assertDontSee('Confirmar análisis');
});

it('el detalle muestra los datos de la entrega, el proveedor y los parámetros analizados', function () {
    $zona = fixture_zona('Zona Norte');
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    fixture_parametro('GRASA');

    $analisis = app(RegistrarAnalisisCalidad::class)->execute(
        entregaId: $entrega->id,
        valores: ['GRASA' => 3.5],
    );

    $response = $this->actingAs($admin)->get(route('admin.analisis-calidad.show', $analisis->id()));

    $response->assertOk();
    $response->assertSee($proveedor->nombres);
    $response->assertSee($proveedor->dni);
    $response->assertSee('Zona Norte');
    $response->assertSee('GRASA');
    $response->assertSee('Análisis en borrador');
    $response->assertSee('Confirmar análisis');
});

it('la pantalla principal muestra la fila del análisis confirmado sin tarjetas de KPIs', function () {
    $zona = fixture_zona('Zona Sur');
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    $analisis = fixture_analisis_confirmado($entrega, 'OBSERVADA', $admin->id);

    $response = $this->actingAs($admin)->get(route('admin.analisis-calidad.index'));

    $response->assertOk();
    $response->assertDontSee('Pendientes de análisis');
    $response->assertSee($proveedor->nombres);
    $response->assertSee('Zona Sur');
    $response->assertSee('calidad-resultado is-observada', false);
    $response->assertSee('Observada');
});

it('la pantalla principal filtra por zona', function () {
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');
    $proveedorA = fixture_proveedor($zonaA);
    $proveedorB = fixture_proveedor($zonaB);
    $acopiadorA = fixture_acopiador($zonaA);
    $acopiadorB = fixture_acopiador($zonaB);
    $admin = fixture_usuario('ADMINISTRADOR');
    $entregaA = fixture_entrega($proveedorA, $acopiadorA, 30.0);
    $entregaB = fixture_entrega($proveedorB, $acopiadorB, 30.0);
    fixture_analisis_confirmado($entregaA, 'ACEPTADA', $admin->id);
    fixture_analisis_confirmado($entregaB, 'ACEPTADA', $admin->id);

    $response = $this->actingAs($admin)->get(route('admin.analisis-calidad.index', ['zona_id' => $zonaA->id]));

    $response->assertOk();
    $response->assertSee($proveedorA->dni);
    $response->assertDontSee($proveedorB->dni);
});
