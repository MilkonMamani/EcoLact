<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la vista de detalle de entrega muestra el estado correcto', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->get(route('admin.entregas.show', $entrega->id));

    $response->assertOk();
    $response->assertSee('Pendiente Analisis');
    $response->assertSee('30.00 L');
});

it('la tabla de proveedores renderiza los datos recibidos del Controller', function () {
    $zona = fixture_zona('Zona Norte');
    $proveedor = fixture_proveedor($zona, '87654321');
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->get(route('admin.proveedores.index'));

    $response->assertOk();
    $response->assertSee('87654321');
    $response->assertSee($proveedor->nombres.' '.$proveedor->apellidos);
});
