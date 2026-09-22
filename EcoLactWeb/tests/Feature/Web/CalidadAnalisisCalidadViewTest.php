<?php

use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pestaña de pendientes muestra las entregas sin análisis', function () {
    $analista = fixture_usuario('CALIDAD');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    fixture_entrega($proveedor, $acopiador, 30.0);

    $response = $this->actingAs($analista)->get(route('calidad.analisis-calidad.index'));

    $response->assertOk();
    $response->assertSee('Pendientes de análisis');
    $response->assertSee('Analizar');
});

it('la pestaña de historial muestra los análisis confirmados', function () {
    $analista = fixture_usuario('CALIDAD');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $analista->id);
    $entrega->update(['estado' => 'ANALIZADA']);

    $response = $this->actingAs($analista)->get(route('calidad.analisis-calidad.index', ['tab' => 'historial']));

    $response->assertOk();
    $response->assertSee('Historial');
});

it('permite registrar valores de un análisis y confirmarlo, calculando el resultado', function () {
    $analista = fixture_usuario('CALIDAD');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);

    $densidad = fixture_parametro('DENSIDAD');
    fixture_regla_calidad($densidad->id, 1.028, 1.034, 'RECHAZADA');

    // La pantalla de "analizar" debe cargar sin error para una entrega pendiente.
    $this->actingAs($analista)->get(route('calidad.entregas.analisis.index', $entrega->id))
        ->assertOk()
        ->assertSee('Parámetros de calidad');

    $this->actingAs($analista)->post(route('calidad.analisis-calidad.store'), [
        'entrega_id' => $entrega->id,
        'valores' => ['DENSIDAD' => 1.030],
    ])->assertRedirect();

    $this->assertDatabaseHas('analisis_calidad', ['entrega_id' => $entrega->id, 'estado' => 'BORRADOR']);

    $analisis = AnalisisCalidadModel::where('entrega_id', $entrega->id)->firstOrFail();

    // Reabrir la pantalla ahora debe ofrecer confirmar el análisis en borrador.
    $this->actingAs($analista)->get(route('calidad.entregas.analisis.index', $entrega->id))
        ->assertOk()
        ->assertSee('borrador')
        ->assertSee('Confirmar análisis');

    $this->actingAs($analista)->patch(route('calidad.analisis-calidad.finalizar', $analisis->id))
        ->assertRedirect();

    $this->assertDatabaseHas('analisis_calidad', [
        'id' => $analisis->id,
        'estado' => 'CONFIRMADO',
        'resultado' => 'ACEPTADA',
    ]);
    $this->assertDatabaseHas('entregas', ['id' => $entrega->id, 'estado' => 'ANALIZADA']);

    // El detalle del historial ya debe mostrar el resultado confirmado.
    $this->actingAs($analista)->get(route('calidad.analisis-calidad.show', $analisis->id))
        ->assertOk()
        ->assertSee('Confirmado')
        ->assertSee('Análisis confirmado. No puede modificarse.')
        ->assertDontSee('Confirmar análisis');
});
