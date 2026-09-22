<?php

use App\Http\Controllers\Web\Calidad\ReglaCalidadController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

require_once __DIR__.'/../../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('store llama a RegistrarReglaCalidad y redirige con éxito', function () {
    Route::post('/_test/reglas-calidad', [ReglaCalidadController::class, 'store']);

    $parametro = fixture_parametro('PH');
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->post('/_test/reglas-calidad', [
        'parametro_id' => $parametro->id,
        'valor_minimo' => 6.2,
        'valor_maximo' => 6.8,
        'resultado_incumplimiento' => 'OBSERVADA',
        'fecha_inicio' => '2026-01-01',
        'estado' => 'ACTIVO',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('reglas_calidad', ['parametro_id' => $parametro->id, 'valor_minimo' => 6.2]);
});

it('update llama a ActualizarReglaCalidad y redirige con éxito', function () {
    Route::put('/_test/reglas-calidad/{regla}', [ReglaCalidadController::class, 'update'])
        ->middleware(SubstituteBindings::class);

    $parametro = fixture_parametro('PH');
    $regla = fixture_regla_calidad($parametro->id, 6.2, 6.8, 'OBSERVADA');
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->put("/_test/reglas-calidad/{$regla->id}", [
        'estado' => 'INACTIVO',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('reglas_calidad', ['id' => $regla->id, 'estado' => 'INACTIVO']);
});

it('CampoActualizable: fecha_fin OMITIDA conserva el valor actual', function () {
    Route::put('/_test/reglas-calidad-omitida/{regla}', [ReglaCalidadController::class, 'update'])
        ->middleware(SubstituteBindings::class);

    $parametro = fixture_parametro('PH');
    $regla = fixture_regla_calidad($parametro->id, 6.2, 6.8, 'OBSERVADA');
    $regla->update(['fecha_fin' => '2026-12-31']);
    $admin = fixture_usuario('ADMINISTRADOR');

    // No se envía fecha_fin en absoluto.
    $response = $this->actingAs($admin)->put("/_test/reglas-calidad-omitida/{$regla->id}", [
        'estado' => 'INACTIVO',
    ]);

    $response->assertRedirect();
    // La columna se persiste como Y-m-d H:i:s aunque el cast del modelo sea
    // 'date' (mismo comportamiento ya documentado en Etapa 07.2).
    $this->assertDatabaseHas('reglas_calidad', ['id' => $regla->id, 'fecha_fin' => '2026-12-31 00:00:00']);
});

it('CampoActualizable: fecha_fin = NULL limpia el valor', function () {
    Route::put('/_test/reglas-calidad-null/{regla}', [ReglaCalidadController::class, 'update'])
        ->middleware(SubstituteBindings::class);

    $parametro = fixture_parametro('PH');
    $regla = fixture_regla_calidad($parametro->id, 6.2, 6.8, 'OBSERVADA');
    $regla->update(['fecha_fin' => '2026-12-31']);
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->put("/_test/reglas-calidad-null/{$regla->id}", [
        'fecha_fin' => null,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('reglas_calidad', ['id' => $regla->id, 'fecha_fin' => null]);
});

it('CampoActualizable: fecha_fin con un valor nuevo lo reemplaza', function () {
    Route::put('/_test/reglas-calidad-valor/{regla}', [ReglaCalidadController::class, 'update'])
        ->middleware(SubstituteBindings::class);

    $parametro = fixture_parametro('PH');
    $regla = fixture_regla_calidad($parametro->id, 6.2, 6.8, 'OBSERVADA');
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->put("/_test/reglas-calidad-valor/{$regla->id}", [
        'fecha_fin' => '2026-06-30',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('reglas_calidad', ['id' => $regla->id, 'fecha_fin' => '2026-06-30 00:00:00']);
});
