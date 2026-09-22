<?php

use App\Http\Controllers\Web\AcopiadorController;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

// Etapa 10.1.1 — Lecturas: se llama al método del Controller directamente
// (sin pasar por el Kernel HTTP) porque todavía no existen vistas Blade
// (Etapa 10.3); así se comprueba la delegación a Application sin depender
// de un renderizado que aún no puede existir.
it('index delega en AcopiadoresListadoQuery y expone el listado con KPIs a la vista', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    registrar_vistas_stub();
    $this->actingAs($admin);

    $view = app(AcopiadorController::class)->index(crear_request_autenticado());

    expect($view->name())->toBe('admin.acopiadores.index');
    expect(collect($view->getData()['datos']->filas)->contains(fn ($f) => $f['id'] === $acopiador->id))->toBeTrue();
    expect($view->getData()['datos']->kpis['activos'])->toBe(1);
});

it('show delega en ObtenerAcopiador y expone el acopiador a la vista', function () {
    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    registrar_vistas_stub();
    $this->actingAs($admin);

    $view = app(AcopiadorController::class)->show(AcopiadorModel::find($acopiador->id));

    expect($view->name())->toBe('admin.acopiadores.show');
    expect($view->getData()['acopiador']->id())->toBe($acopiador->id);
});

it('store llama a RegistrarAcopiador y redirige con éxito', function () {
    Route::post('/_test/acopiadores', [AcopiadorController::class, 'store']);

    fixture_rol('ACOPIADOR');
    $zona = fixture_zona();
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->post('/_test/acopiadores', [
        'dni' => '11112222',
        'nombres' => 'Marco',
        'apellidos' => 'Flores',
        'zona_id' => $zona->id,
        'username' => 'marco.flores',
        'password' => 'Secreto#123',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('acopiadores', ['dni' => '11112222']);
});

it('update llama a ActualizarAcopiador y redirige con éxito', function () {
    Route::put('/_test/acopiadores/{acopiador}', [AcopiadorController::class, 'update'])
        ->middleware(SubstituteBindings::class);

    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->put("/_test/acopiadores/{$acopiador->id}", [
        'nombres' => 'Nombre Actualizado',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('acopiadores', ['id' => $acopiador->id, 'nombres' => 'Nombre Actualizado']);
});

it('desactivar llama a DesactivarAcopiador y redirige con éxito', function () {
    Route::post('/_test/acopiadores/{acopiador}/desactivar', [AcopiadorController::class, 'desactivar'])
        ->middleware(SubstituteBindings::class);

    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->post("/_test/acopiadores/{$acopiador->id}/desactivar");

    $response->assertRedirect();
    $this->assertDatabaseHas('acopiadores', ['id' => $acopiador->id, 'estado' => 'INACTIVO']);
});

it('reactivar llama a ReactivarAcopiador con la zona indicada y redirige con éxito', function () {
    Route::post('/_test/acopiadores/{acopiador}/reactivar', [AcopiadorController::class, 'reactivar'])
        ->middleware(SubstituteBindings::class);

    $zonaOrigen = fixture_zona('Zona Origen');
    $zonaDestino = fixture_zona('Zona Destino');
    $acopiador = fixture_acopiador($zonaOrigen);
    $acopiador->update(['estado' => 'INACTIVO']);
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->post("/_test/acopiadores/{$acopiador->id}/reactivar", [
        'zona_id' => $zonaDestino->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('acopiadores', ['id' => $acopiador->id, 'estado' => 'ACTIVO', 'zona_id' => $zonaDestino->id]);
    $this->assertDatabaseHas('acopiador_zona_historial', [
        'acopiador_id' => $acopiador->id,
        'zona_id' => $zonaDestino->id,
        'fecha_fin' => null,
    ]);
});
