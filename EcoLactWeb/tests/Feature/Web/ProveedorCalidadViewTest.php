<?php

use App\Infrastructure\Persistence\Models\AnalisisCalidadDetalleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla mi calidad muestra los KPIs de resultados', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    $entrega = fixture_entrega($proveedor, $acopiador, 20.0);
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $admin->id);

    $response = $this->actingAs($proveedor->user)->get(route('proveedor.analisis.index'));

    $response->assertOk();
    $response->assertSee('Aceptados');
    $response->assertSee('Observados');
    $response->assertSee('Rechazados');
});

it('el detalle de un análisis muestra los parámetros y el resultado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    $entrega = fixture_entrega($proveedor, $acopiador, 20.0);
    $analisis = fixture_analisis_confirmado($entrega, 'OBSERVADA', $admin->id);

    $parametro = fixture_parametro('DENSIDAD');
    AnalisisCalidadDetalleModel::create([
        'analisis_id' => $analisis->id,
        'parametro_id' => $parametro->id,
        'valor' => 1.03,
        'origen' => 'MANUAL',
        'cumple' => true,
    ]);

    $response = $this->actingAs($proveedor->user)->get(route('proveedor.analisis.show', $analisis->id));

    $response->assertOk();
    $response->assertSee('Parámetros de calidad');
    $response->assertSee('DENSIDAD');
    $response->assertSee('Observada');
});
