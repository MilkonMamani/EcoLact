<?php

use App\Application\Calidad\RegistrarAnalisisCalidad;
use App\Http\Controllers\Web\Calidad\AnalisisCalidadController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

require_once __DIR__.'/../../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('index delega en EntregasCalidadListadoQuery y expone una página de entregas con su análisis a la vista', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    fixture_parametro('GRASA');
    $admin = fixture_usuario('ADMINISTRADOR');

    app(RegistrarAnalisisCalidad::class)->execute(entregaId: $entrega->id, valores: ['GRASA' => 3.5]);

    registrar_vistas_stub();
    $this->actingAs($admin);

    $view = app(AnalisisCalidadController::class)->index(crear_request_autenticado());

    expect($view->name())->toBe('admin.analisis-calidad.index');
    expect($view->getData()['datos']->filas->total())->toBe(1);
    expect($view->getData()['datos']->filas->items()[0]['estado'])->toBe('BORRADOR');
    expect($view->getData()['porPagina'])->toBe(25);
});

it('store llama a RegistrarAnalisisCalidad y redirige con éxito', function () {
    Route::post('/_test/analisis-calidad', [AnalisisCalidadController::class, 'store']);

    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    fixture_parametro('GRASA');
    $analista = fixture_usuario('CALIDAD');

    $response = $this->actingAs($analista)->post('/_test/analisis-calidad', [
        'entrega_id' => $entrega->id,
        'valores' => ['GRASA' => 3.5],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('analisis_calidad', ['entrega_id' => $entrega->id, 'estado' => 'BORRADOR']);
});

it('finalizar llama a FinalizarAnalisisCalidad usando al usuario autenticado y redirige con éxito', function () {
    Route::post('/_test/analisis-calidad/{analisis}/finalizar', [AnalisisCalidadController::class, 'finalizar'])
        ->middleware(SubstituteBindings::class);

    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    $parametro = fixture_parametro('GRASA');
    fixture_regla_calidad($parametro->id, 3.0, 6.0, 'OBSERVADA');

    $analista = fixture_usuario('CALIDAD');

    $analisis = app(RegistrarAnalisisCalidad::class)->execute(
        entregaId: $entrega->id,
        valores: ['GRASA' => 3.5],
    );

    $response = $this->actingAs($analista)->post("/_test/analisis-calidad/{$analisis->id()}/finalizar");

    $response->assertRedirect();
    $this->assertDatabaseHas('analisis_calidad', [
        'id' => $analisis->id(),
        'estado' => 'CONFIRMADO',
        'confirmado_por_user_id' => $analista->id,
    ]);
});

it('finalizar ignora un confirmado_por_user_id suplantado y usa siempre al usuario autenticado (actor seguro)', function () {
    Route::post('/_test/analisis-calidad/{analisis}/finalizar-actor-seguro', [AnalisisCalidadController::class, 'finalizar'])
        ->middleware(SubstituteBindings::class);

    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    $parametro = fixture_parametro('GRASA');
    fixture_regla_calidad($parametro->id, 3.0, 6.0, 'OBSERVADA');
    $otroUsuario = fixture_usuario('ADMINISTRADOR');
    $analista = fixture_usuario('CALIDAD');

    $analisis = app(RegistrarAnalisisCalidad::class)->execute(
        entregaId: $entrega->id,
        valores: ['GRASA' => 3.5],
    );

    $response = $this->actingAs($analista)->post(
        "/_test/analisis-calidad/{$analisis->id()}/finalizar-actor-seguro",
        // Un cliente malicioso intenta hacer figurar a otro usuario como
        // quien confirmó el análisis.
        ['confirmado_por_user_id' => $otroUsuario->id]
    );

    $response->assertRedirect();
    $this->assertDatabaseHas('analisis_calidad', [
        'id' => $analisis->id(),
        'confirmado_por_user_id' => $analista->id,
    ]);
});

it('finalizar sigue rechazando un análisis ya CONFIRMADO (403), incluso para ADMINISTRADOR', function () {
    Route::post('/_test/analisis-calidad/{analisis}/finalizar-2', [AnalisisCalidadController::class, 'finalizar'])
        ->middleware(SubstituteBindings::class);

    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    $admin = fixture_usuario('ADMINISTRADOR');
    $analisis = fixture_analisis_confirmado($entrega, 'ACEPTADA', $admin->id);

    $response = $this->actingAs($admin)->post("/_test/analisis-calidad/{$analisis->id}/finalizar-2");

    $response->assertForbidden();
});
