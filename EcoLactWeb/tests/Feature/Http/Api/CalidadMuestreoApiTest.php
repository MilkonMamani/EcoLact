<?php

use App\Application\Calidad\SeleccionarMuestraCalidad;
use App\Domain\Shared\Enums\MetodoSeleccionMuestreo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

// Estas pruebas no tratan de asistencia: el personal ya tiene su jornada PRESENTE de hoy.
beforeEach(fn () => fixture_personal_con_asistencia_hoy());

it('lista solo las muestras pendientes con sus KPIs', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $analista = fixture_usuario('CALIDAD');
    $seleccionada = fixture_entrega(fixture_proveedor($zona), $acopiador, 45.0);
    fixture_entrega(fixture_proveedor($zona), $acopiador, 38.0);

    app(SeleccionarMuestraCalidad::class)->execute($seleccionada->id, $analista->id, MetodoSeleccionMuestreo::MANUAL);

    $this->actingAs($analista, 'sanctum')->getJson(route('api.calidad.muestreos.index'))
        ->assertOk()
        ->assertJsonPath('data.kpis.pendientes', 1)
        ->assertJsonCount(1, 'data.filas')
        ->assertJsonPath('data.filas.0.entregaId', $seleccionada->id);
});

it('sortea en el servidor y solo acepta ALEATORIA con el token de ese sorteo', function () {
    $zona = fixture_zona();
    $entrega = fixture_entrega(fixture_proveedor($zona), fixture_acopiador($zona), 42.0);
    $analista = fixture_usuario('CALIDAD');

    $sorteo = $this->actingAs($analista, 'sanctum')->postJson(route('api.calidad.muestreos.aleatorio'))
        ->assertOk()
        ->assertJsonPath('data.entrega.entregaId', $entrega->id);

    $this->postJson(route('api.calidad.muestreos.store'), [
        'entrega_id' => $entrega->id,
        'metodo_seleccion' => 'ALEATORIA',
        'propuesta_token' => 'falso',
    ])->assertStatus(422);

    $this->postJson(route('api.calidad.muestreos.store'), [
        'entrega_id' => $entrega->id,
        'metodo_seleccion' => 'ALEATORIA',
        'propuesta_token' => $sorteo->json('data.propuesta_token'),
    ])->assertCreated()->assertJsonPath('data.metodoSeleccion', 'ALEATORIA');
});

it('responde 409 si otro analista ya seleccionó la entrega (conflicto offline)', function () {
    $zona = fixture_zona();
    $entrega = fixture_entrega(fixture_proveedor($zona), fixture_acopiador($zona), 42.0);
    $primero = fixture_usuario('CALIDAD');
    $segundo = fixture_usuario('CALIDAD');

    app(SeleccionarMuestraCalidad::class)->execute($entrega->id, $primero->id, MetodoSeleccionMuestreo::MANUAL);

    $this->actingAs($segundo, 'sanctum')->postJson(route('api.calidad.muestreos.store'), [
        'entrega_id' => $entrega->id,
        'metodo_seleccion' => 'MANUAL',
        'tipo_control' => 'DIRIGIDO',
    ])->assertStatus(409)->assertJsonPath('message', 'Esta entrega ya fue seleccionada para análisis.');

    $this->assertDatabaseCount('muestreos_calidad', 1);
});

it('entrega las reglas vigentes para la vista previa del resultado', function () {
    $zona = fixture_zona();
    $entrega = fixture_entrega(fixture_proveedor($zona), fixture_acopiador($zona), 42.0);
    $parametro = fixture_parametro('GRASA');
    fixture_regla_calidad($parametro->id, 3.0, 6.0, 'OBSERVADA');

    $this->actingAs(fixture_usuario('CALIDAD'), 'sanctum')
        ->getJson(route('api.calidad.entregas.analisis-calidad.show', $entrega->id))
        ->assertOk()
        ->assertJsonPath('data.reglas.0.codigo', 'GRASA')
        ->assertJsonPath('data.reglas.0.resultado_incumplimiento', 'OBSERVADA');
});

it('un error de negocio al finalizar responde 422, no 500 (la cola offline no lo reintenta en bucle)', function () {
    $zona = fixture_zona();
    $entrega = fixture_entrega(fixture_proveedor($zona), fixture_acopiador($zona), 42.0);
    fixture_parametro('LACTOSA'); // sin regla vigente
    $analista = fixture_usuario('CALIDAD');
    fixture_muestra($entrega, $analista->id);

    $borrador = $this->actingAs($analista, 'sanctum')->postJson(route('api.calidad.analisis-calidad.store'), [
        'entrega_id' => $entrega->id,
        'valores' => ['LACTOSA' => 4.6],
        'fecha_analisis' => now()->subMinutes(20)->toIso8601String(),
    ])->assertCreated();

    $this->patchJson(route('api.calidad.analisis-calidad.finalizar', $borrador->json('data.id')))->assertStatus(422);
});
