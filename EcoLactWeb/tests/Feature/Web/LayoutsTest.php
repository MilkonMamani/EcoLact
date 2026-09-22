<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

// Etapa 10.3 — Blade + Frontend Web: las vistas ya existen y se renderizan
// a través del ciclo HTTP real (a diferencia de los tests de Etapa 10.1/10.2
// que llamaban al Controller directamente con vistas stub).
it('el layout admin renderiza y muestra el menú administrativo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->get(route('admin.zonas.index'));

    $response->assertOk();
    $response->assertSee('EcoLact');
    $response->assertSee('Proveedores');
    $response->assertSee('Acopiadores');
    $response->assertSee('Finanzas');
    $response->assertSee('Configuración');
});

it('el layout acopiador renderiza y NO muestra pagos ni configuración administrativa', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);

    $response = $this->actingAs($acopiador->user)->get(route('acopiador.proveedores.index'));

    $response->assertOk();
    $response->assertSee('EcoLact');
    $response->assertSee('Proveedores de mi zona');
    $response->assertDontSee('Finanzas');
    $response->assertDontSee('Configuración');
    $response->assertDontSee('Zonas');
});

it('el layout proveedor renderiza y NO muestra opciones de administración', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);

    $response = $this->actingAs($proveedor->user)->get(route('proveedor.perfil'));

    $response->assertOk();
    $response->assertSee('EcoLact');
    $response->assertSee('Mis liquidaciones');
    $response->assertDontSee('Administración');
    $response->assertDontSee('Acopiadores');
});
