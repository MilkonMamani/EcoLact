<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

// Estas pruebas no tratan de asistencia: el personal ya tiene su jornada PRESENTE de hoy.
beforeEach(fn () => fixture_personal_con_asistencia_hoy());

it('la pantalla de inventario de insumos muestra los KPIs y distingue niveles de stock', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    fixture_insumo('Cuajo', 'ml', 50.0, 100.0);

    $response = $this->actingAs($encargado)->get(route('planta.insumos.index'));

    $response->assertOk();
    $response->assertSee('Cuajo');
    $response->assertSee('Stock bajo');
});

it('un encargado de planta puede registrar un movimiento de entrada y salida', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $insumo = fixture_insumo('Sal', 'kg', 20.0, 5.0);

    $entrada = $this->actingAs($encargado)->post(route('planta.insumos.movimiento', $insumo->id()), [
        'tipo' => 'ENTRADA',
        'motivo' => 'COMPRA',
        'cantidad' => 10.0,
    ]);
    $entrada->assertRedirect(route('planta.insumos.show', $insumo->id()));
    $this->assertDatabaseHas('insumos', ['id' => $insumo->id(), 'stock_actual' => 30.0]);

    $salida = $this->actingAs($encargado)->post(route('planta.insumos.movimiento', $insumo->id()), [
        'tipo' => 'SALIDA',
        'motivo' => 'MERMA',
        'cantidad' => 25.0,
    ]);
    $salida->assertRedirect(route('planta.insumos.show', $insumo->id()));
    $this->assertDatabaseHas('insumos', ['id' => $insumo->id(), 'stock_actual' => 5.0]);
});

it('no permite registrar una salida mayor al stock disponible', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $insumo = fixture_insumo('Envases', 'unidad', 10.0, 100.0);

    $response = $this->actingAs($encargado)->post(route('planta.insumos.movimiento', $insumo->id()), [
        'tipo' => 'SALIDA',
        'motivo' => 'AJUSTE',
        'cantidad' => 50.0,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('error');
    $this->assertDatabaseHas('insumos', ['id' => $insumo->id(), 'stock_actual' => 10.0]);
});

it('la pantalla de movimientos muestra el historial completo y filtra por insumo', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $insumoA = fixture_insumo('Cultivo A', 'kg', 20.0, 5.0);
    $insumoB = fixture_insumo('Cultivo B', 'kg', 20.0, 5.0);

    $this->actingAs($encargado)->post(route('planta.insumos.movimiento', $insumoA->id()), [
        'tipo' => 'ENTRADA', 'motivo' => 'COMPRA', 'cantidad' => 5.0,
    ]);
    $this->actingAs($encargado)->post(route('planta.insumos.movimiento', $insumoB->id()), [
        'tipo' => 'ENTRADA', 'motivo' => 'COMPRA', 'cantidad' => 3.0,
    ]);

    $response = $this->actingAs($encargado)->get(route('planta.insumos.movimientos'));
    $response->assertOk();
    $response->assertSee('Cultivo A');
    $response->assertSee('Cultivo B');

    // El selector de filtro por insumo siempre lista todos los insumos (para
    // poder cambiar el filtro), así que "Cultivo B" seguirá apareciendo como
    // opción del <select>; lo que debe cambiar es el listado de movimientos,
    // por eso se comprueba con las cantidades (+5 vs +3) en vez del
    // nombre del insumo.
    $filtrado = $this->actingAs($encargado)->get(route('planta.insumos.movimientos', ['insumo_id' => $insumoA->id()]));
    $filtrado->assertOk();
    $filtrado->assertSee('+5');
    $filtrado->assertDontSee('+3');
});
