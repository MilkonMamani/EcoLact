<?php

use App\Http\Controllers\Web\ProveedorController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

// Etapa 10.1.2 — Scope de listados: index() se llama directamente en el
// Controller (sin pasar por el Kernel HTTP) porque todavía no existen
// vistas Blade (Etapa 10.3).
it('index (ADMINISTRADOR) ve proveedores de varias zonas y selecciona el primero', function () {
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');
    $proveedorA = fixture_proveedor($zonaA, '11112222');
    $proveedorB = fixture_proveedor($zonaB, '33334444');
    $admin = fixture_usuario('ADMINISTRADOR');

    registrar_vistas_stub();
    $this->actingAs($admin);

    $view = app(ProveedorController::class)->index(crear_request_autenticado());

    $dnis = $view->getData()['datos']->filas->getCollection()->pluck('dni');
    expect($dnis)->toContain('11112222')->toContain('33334444');
    expect($view->getData()['panel']->id)->toBe($view->getData()['datos']->filas->first()['id']);
});

it('index (ACOPIADOR Zona A) NO recibe proveedores de Zona B', function () {
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');
    $proveedorA = fixture_proveedor($zonaA);
    $proveedorB = fixture_proveedor($zonaB);
    $acopiadorA = fixture_acopiador($zonaA);

    registrar_vistas_stub();
    $this->actingAs($acopiadorA->user);

    // Un ACOPIADOR malicioso intenta forzar la zona de otro vía query.
    $view = app(ProveedorController::class)->index(crear_request_autenticado('/proveedores', ['zona_id' => $zonaB->id]));

    $ids = $view->getData()['datos']->filas->getCollection()->pluck('id');
    expect($ids)->toContain($proveedorA->id)->not->toContain($proveedorB->id);
});

it('store llama a RegistrarProveedor y redirige con éxito', function () {
    Route::post('/_test/proveedores', [ProveedorController::class, 'store']);

    fixture_rol('PROVEEDOR');
    $zona = fixture_zona();
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->post('/_test/proveedores', [
        'dni' => '12345678',
        'nombres' => 'Ana',
        'apellidos' => 'Quispe',
        'telefono' => '70000000',
        'zona_id' => $zona->id,
        'username' => 'ana.quispe',
        'password' => 'Secreto#123',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('proveedores', ['dni' => '12345678']);
    $this->assertDatabaseHas('users', ['username' => 'ana.quispe']);
});

it('update llama a ActualizarProveedor y redirige con éxito', function () {
    Route::put('/_test/proveedores/{proveedor}', [ProveedorController::class, 'update'])
        ->middleware(SubstituteBindings::class);

    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->put("/_test/proveedores/{$proveedor->id}", [
        'nombres' => 'Nombre Actualizado',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('proveedores', ['id' => $proveedor->id, 'nombres' => 'Nombre Actualizado']);
});

it('desactivar llama a DesactivarProveedor y redirige con éxito', function () {
    Route::post('/_test/proveedores/{proveedor}/desactivar', [ProveedorController::class, 'desactivar'])
        ->middleware(SubstituteBindings::class);

    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->post("/_test/proveedores/{$proveedor->id}/desactivar");

    $response->assertRedirect();
    $this->assertDatabaseHas('proveedores', ['id' => $proveedor->id, 'estado' => 'INACTIVO']);
});

it('store aplica la Policy: un ACOPIADOR no puede registrar proveedores (403)', function () {
    Route::post('/_test/proveedores-403', [ProveedorController::class, 'store']);

    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);

    $response = $this->actingAs($acopiador->user)->post('/_test/proveedores-403', [
        'dni' => '99999999',
        'nombres' => 'X',
        'apellidos' => 'Y',
        'zona_id' => $zona->id,
        'username' => 'x.y',
        'password' => 'Secreto#123',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('proveedores', ['dni' => '99999999']);
});
