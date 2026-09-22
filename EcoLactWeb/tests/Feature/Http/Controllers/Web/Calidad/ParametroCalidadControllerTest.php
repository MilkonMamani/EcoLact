<?php

use App\Http\Controllers\Web\Calidad\ParametroCalidadController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

require_once __DIR__.'/../../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('store llama a RegistrarParametroCalidad y redirige con éxito', function () {
    Route::post('/_test/parametros-calidad', [ParametroCalidadController::class, 'store']);

    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->post('/_test/parametros-calidad', [
        'codigo' => 'UREA',
        'nombre' => 'Urea',
        'unidad' => 'mg/dL',
        'estado' => 'ACTIVO',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('parametros_calidad', ['codigo' => 'UREA']);
});

it('update llama a ActualizarParametroCalidad y redirige con éxito', function () {
    Route::put('/_test/parametros-calidad/{parametro}', [ParametroCalidadController::class, 'update'])
        ->middleware(SubstituteBindings::class);

    $parametro = fixture_parametro('GRASA');
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->put("/_test/parametros-calidad/{$parametro->id}", [
        'nombre' => 'Grasa Butírica',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('parametros_calidad', ['id' => $parametro->id, 'nombre' => 'Grasa Butírica', 'codigo' => 'GRASA']);
});

it('un ACOPIADOR no puede registrar parámetros de calidad (403)', function () {
    Route::post('/_test/parametros-calidad-403', [ParametroCalidadController::class, 'store']);

    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);

    $response = $this->actingAs($acopiador->user)->post('/_test/parametros-calidad-403', [
        'codigo' => 'UREA',
        'nombre' => 'Urea',
        'estado' => 'ACTIVO',
    ]);

    $response->assertForbidden();
});
