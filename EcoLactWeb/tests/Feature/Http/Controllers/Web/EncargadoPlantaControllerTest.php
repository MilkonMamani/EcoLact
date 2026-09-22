<?php

use App\Http\Controllers\Web\EncargadoPlantaController;
use App\Infrastructure\Persistence\Models\EncargadoPlantaModel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function fixture_encargado_planta(): EncargadoPlantaModel
{
    $user = fixture_usuario('ENCARGADO_PLANTA');

    return EncargadoPlantaModel::create([
        'user_id' => $user->id,
        'dni' => fixture_next_dni(),
        'nombres' => 'Encargado',
        'apellidos' => 'Test',
        'telefono' => null,
        'estado' => 'ACTIVO',
        'fecha_ingreso' => now(),
    ]);
}

it('index delega en ObtenerEncargadosPlanta y expone el listado a la vista', function () {
    $encargado = fixture_encargado_planta();
    $admin = fixture_usuario('ADMINISTRADOR');

    registrar_vistas_stub();
    $this->actingAs($admin);

    $view = app(EncargadoPlantaController::class)->index();

    expect($view->name())->toBe('admin.encargados-planta.index');
    expect(collect($view->getData()['encargados'])->contains(fn ($e) => $e->id() === $encargado->id))->toBeTrue();
});

it('show delega en ObtenerEncargadoPlanta y expone el encargado a la vista', function () {
    $encargado = fixture_encargado_planta();
    $admin = fixture_usuario('ADMINISTRADOR');

    registrar_vistas_stub();
    $this->actingAs($admin);

    $view = app(EncargadoPlantaController::class)->show($encargado);

    expect($view->name())->toBe('admin.encargados-planta.show');
    expect($view->getData()['encargado']->id())->toBe($encargado->id);
});

it('store llama a RegistrarEncargadoPlanta y redirige con éxito', function () {
    fixture_rol('ENCARGADO_PLANTA');
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->post(route('admin.encargados-planta.store'), [
        'dni' => '11112222',
        'nombres' => 'Marco',
        'apellidos' => 'Flores',
        'username' => 'marco.flores',
        'password' => 'Secreto#123',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('encargados_planta', ['dni' => '11112222']);
});

it('update llama a ActualizarEncargadoPlanta y redirige con éxito', function () {
    $encargado = fixture_encargado_planta();
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->patch(route('admin.encargados-planta.update', $encargado), [
        'nombres' => 'Nombre Actualizado',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('encargados_planta', ['id' => $encargado->id, 'nombres' => 'Nombre Actualizado']);
});

it('desactivar llama a DesactivarEncargadoPlanta y redirige con éxito', function () {
    $encargado = fixture_encargado_planta();
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->patch(route('admin.encargados-planta.desactivar', $encargado));

    $response->assertRedirect();
    $this->assertDatabaseHas('encargados_planta', ['id' => $encargado->id, 'estado' => 'INACTIVO']);
});

it('reactivar llama a ReactivarEncargadoPlanta y redirige con éxito', function () {
    $encargado = fixture_encargado_planta();
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->patch(route('admin.encargados-planta.desactivar', $encargado));

    $response = $this->actingAs($admin)->patch(route('admin.encargados-planta.reactivar', $encargado));

    $response->assertRedirect();
    $this->assertDatabaseHas('encargados_planta', ['id' => $encargado->id, 'estado' => 'ACTIVO']);
});

it('un ENCARGADO_PLANTA no puede listar a otros encargados de planta', function () {
    fixture_encargado_planta();
    $otroEncargado = fixture_usuario('ENCARGADO_PLANTA');

    registrar_vistas_stub();
    $this->actingAs($otroEncargado);

    app(EncargadoPlantaController::class)->index();
})->throws(AuthorizationException::class);
