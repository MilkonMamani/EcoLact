<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla de proveedores de mi zona muestra los KPIs y el listado', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    fixture_entrega($proveedor, $acopiador, 20.0);

    $response = $this->actingAs($acopiador->user)->get(route('acopiador.proveedores.index'));

    $response->assertOk();
    $response->assertSee('Proveedor Test');
    $response->assertSee('Activos');
});

it('el detalle de un proveedor muestra el resumen de entregas y el enlace al QR', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    fixture_entrega($proveedor, $acopiador, 20.0);

    $response = $this->actingAs($acopiador->user)->get(route('acopiador.proveedores.show', $proveedor->id));

    $response->assertOk();
    $response->assertSee('Resumen de entregas');
    $response->assertSee('Ver QR');
    $response->assertDontSee($proveedor->qr_token);
});

it('la pantalla del QR no expone el qr_token como texto plano', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    $response = $this->actingAs($acopiador->user)->get(route('acopiador.proveedores.qr', $proveedor->id));

    $response->assertOk();
    $response->assertSee('Código QR disponible');
    $response->assertDontSee($proveedor->qr_token);
});

it('un acopiador no puede ver un proveedor de otra zona (403)', function () {
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');
    $proveedorB = fixture_proveedor($zonaB);
    $acopiadorA = fixture_acopiador($zonaA);

    $this->actingAs($acopiadorA->user)->get(route('acopiador.proveedores.show', $proveedorB->id))->assertForbidden();
});
