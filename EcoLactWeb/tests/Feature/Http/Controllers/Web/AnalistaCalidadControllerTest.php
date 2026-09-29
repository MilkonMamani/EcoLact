<?php

use App\Application\Trabajador\TipoFichaPersonal;
use App\Http\Controllers\Web\AnalistaCalidadController;
use App\Infrastructure\Persistence\Models\AnalistaCalidadModel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function fixture_analista_calidad(): AnalistaCalidadModel
{
    $user = fixture_usuario('CALIDAD');

    return AnalistaCalidadModel::create([
        'user_id' => $user->id,
        'dni' => fixture_next_dni(),
        'nombres' => 'Analista',
        'apellidos' => 'Test',
        'telefono' => null,
        'estado' => 'ACTIVO',
    ]);
}

it('index pinta la pantalla compartida con el listado y el panel del primero', function () {
    $ficha = fixture_analista_calidad();
    $admin = fixture_usuario('ADMINISTRADOR');

    registrar_vistas_stub();
    $this->actingAs($admin);

    $view = app(AnalistaCalidadController::class)->index(request());

    expect($view->name())->toBe('admin.fichas-personal.index');
    expect($view->getData()['config']['tipo'])->toBe(TipoFichaPersonal::ANALISTA_CALIDAD);
    expect(collect($view->getData()['filas']->items())->pluck('id')->all())->toBe([$ficha->id]);
    expect($view->getData()['panel']->id)->toBe($ficha->id);
});

it('show ya no es una página aparte: redirige al listado con el registro seleccionado', function () {
    $ficha = fixture_analista_calidad();
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->get(route('admin.analistas-calidad.show', $ficha))
        ->assertRedirect(route('admin.analistas-calidad.index', ['analista' => $ficha->id]));
});

it('store llama a RegistrarAnalistaCalidad y redirige con éxito', function () {
    fixture_rol('CALIDAD');
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->post(route('admin.analistas-calidad.store'), [
        'dni' => '11112222',
        'nombres' => 'Marco',
        'apellidos' => 'Flores',
        'username' => 'marco.flores',
        'password' => 'Secreto#123',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('analistas_calidad', ['dni' => '11112222']);
});

it('update llama a ActualizarAnalistaCalidad y redirige con éxito', function () {
    $analista = fixture_analista_calidad();
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->patch(route('admin.analistas-calidad.update', $analista), [
        'nombres' => 'Nombre Actualizado',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('analistas_calidad', ['id' => $analista->id, 'nombres' => 'Nombre Actualizado']);
});

it('desactivar llama a DesactivarAnalistaCalidad y redirige con éxito', function () {
    $analista = fixture_analista_calidad();
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->patch(route('admin.analistas-calidad.desactivar', $analista));

    $response->assertRedirect();
    $this->assertDatabaseHas('analistas_calidad', ['id' => $analista->id, 'estado' => 'INACTIVO']);
});

it('reactivar llama a ReactivarAnalistaCalidad y redirige con éxito', function () {
    $analista = fixture_analista_calidad();
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->patch(route('admin.analistas-calidad.desactivar', $analista));

    $response = $this->actingAs($admin)->patch(route('admin.analistas-calidad.reactivar', $analista));

    $response->assertRedirect();
    $this->assertDatabaseHas('analistas_calidad', ['id' => $analista->id, 'estado' => 'ACTIVO']);
});

it('un analista de CALIDAD no puede listar a otros analistas de calidad', function () {
    fixture_analista_calidad();
    $otroAnalista = fixture_usuario('CALIDAD');

    registrar_vistas_stub();
    $this->actingAs($otroAnalista);

    app(AnalistaCalidadController::class)->index(request());
})->throws(AuthorizationException::class);
