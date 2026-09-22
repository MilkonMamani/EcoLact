<?php

use App\Http\Controllers\Web\PersonalVentasController;
use App\Infrastructure\Persistence\Models\PersonalVentasModel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function fixture_personal_ventas(): PersonalVentasModel
{
    $user = fixture_usuario('VENTAS');

    return PersonalVentasModel::create([
        'user_id' => $user->id,
        'dni' => fixture_next_dni(),
        'nombres' => 'Personal',
        'apellidos' => 'Test',
        'telefono' => null,
        'estado' => 'ACTIVO',
        'fecha_ingreso' => now(),
    ]);
}

it('index delega en ObtenerListaPersonalVentas y expone el listado a la vista', function () {
    $personal = fixture_personal_ventas();
    $admin = fixture_usuario('ADMINISTRADOR');

    registrar_vistas_stub();
    $this->actingAs($admin);

    $view = app(PersonalVentasController::class)->index();

    expect($view->name())->toBe('admin.personal-ventas.index');
    expect(collect($view->getData()['personal'])->contains(fn ($p) => $p->id() === $personal->id))->toBeTrue();
});

it('show delega en ObtenerPersonalVentas y expone el registro a la vista', function () {
    $personal = fixture_personal_ventas();
    $admin = fixture_usuario('ADMINISTRADOR');

    registrar_vistas_stub();
    $this->actingAs($admin);

    $view = app(PersonalVentasController::class)->show($personal);

    expect($view->name())->toBe('admin.personal-ventas.show');
    expect($view->getData()['personal']->id())->toBe($personal->id);
});

it('store llama a RegistrarPersonalVentas y redirige con éxito', function () {
    fixture_rol('VENTAS');
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->post(route('admin.personal-ventas.store'), [
        'dni' => '11112222',
        'nombres' => 'Marco',
        'apellidos' => 'Flores',
        'username' => 'marco.flores',
        'password' => 'Secreto#123',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('personal_ventas', ['dni' => '11112222']);
});

it('update llama a ActualizarPersonalVentas y redirige con éxito', function () {
    $personal = fixture_personal_ventas();
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->patch(route('admin.personal-ventas.update', $personal), [
        'nombres' => 'Nombre Actualizado',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('personal_ventas', ['id' => $personal->id, 'nombres' => 'Nombre Actualizado']);
});

it('desactivar llama a DesactivarPersonalVentas y redirige con éxito', function () {
    $personal = fixture_personal_ventas();
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->patch(route('admin.personal-ventas.desactivar', $personal));

    $response->assertRedirect();
    $this->assertDatabaseHas('personal_ventas', ['id' => $personal->id, 'estado' => 'INACTIVO']);
});

it('reactivar llama a ReactivarPersonalVentas y redirige con éxito', function () {
    $personal = fixture_personal_ventas();
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->patch(route('admin.personal-ventas.desactivar', $personal));

    $response = $this->actingAs($admin)->patch(route('admin.personal-ventas.reactivar', $personal));

    $response->assertRedirect();
    $this->assertDatabaseHas('personal_ventas', ['id' => $personal->id, 'estado' => 'ACTIVO']);
});

it('un miembro de VENTAS no puede listar a otro personal de ventas', function () {
    fixture_personal_ventas();
    $otroVentas = fixture_usuario('VENTAS');

    registrar_vistas_stub();
    $this->actingAs($otroVentas);

    app(PersonalVentasController::class)->index();
})->throws(AuthorizationException::class);
