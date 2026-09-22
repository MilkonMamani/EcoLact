<?php

use App\Http\Controllers\Web\EntregaController;
use App\Infrastructure\Persistence\Models\EntregaModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

// Etapa 10.1.1 — Lectura: se llama al método del Controller directamente
// porque todavía no existen vistas Blade (Etapa 10.3).
it('show delega en ObtenerEntrega y expone la entrega a la vista', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);
    $admin = fixture_usuario('ADMINISTRADOR');

    registrar_vistas_stub();
    $this->actingAs($admin);

    $view = app(EntregaController::class)->show(EntregaModel::find($entrega->id));

    expect($view->name())->toBe('admin.entregas.show');
    expect($view->getData()['entrega']->id())->toBe($entrega->id);
});

// Etapa 10.1.2 — Scope de listados.
it('index (ACOPIADOR Zona A) NO recibe entregas de Zona B', function () {
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');
    $proveedorA = fixture_proveedor($zonaA);
    $proveedorB = fixture_proveedor($zonaB);
    $acopiadorA = fixture_acopiador($zonaA);
    $acopiadorB = fixture_acopiador($zonaB);
    $entregaA = fixture_entrega($proveedorA, $acopiadorA, 20.0);
    $entregaB = fixture_entrega($proveedorB, $acopiadorB, 20.0);

    registrar_vistas_stub();
    $this->actingAs($acopiadorA->user);

    // Un ACOPIADOR malicioso intenta forzar la zona de otro vía query.
    $view = app(EntregaController::class)->index(crear_request_autenticado('/entregas', ['zona_id' => $zonaB->id]));

    $ids = collect($view->getData()['entregas'])->map(fn ($e) => $e->id());
    expect($ids)->toContain($entregaA->id)->not->toContain($entregaB->id);
});

it('index (PROVEEDOR A) NO recibe entregas del Proveedor B', function () {
    $zona = fixture_zona();
    $proveedorA = fixture_proveedor($zona);
    $proveedorB = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entregaA = fixture_entrega($proveedorA, $acopiador, 20.0);
    $entregaB = fixture_entrega($proveedorB, $acopiador, 20.0);

    registrar_vistas_stub();
    $this->actingAs($proveedorA->user);

    // Un PROVEEDOR malicioso intenta ver toda la zona vía query, en vez de
    // solo sus propias entregas.
    $view = app(EntregaController::class)->index(crear_request_autenticado('/entregas', ['zona_id' => $zona->id]));

    $ids = collect($view->getData()['entregas'])->map(fn ($e) => $e->id());
    expect($ids)->toContain($entregaA->id)->not->toContain($entregaB->id);
});

it('store ignora un acopiador_user_id suplantado y usa siempre al acopiador autenticado (actor seguro)', function () {
    Route::post('/_test/entregas-actor-seguro', [EntregaController::class, 'store']);

    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiadorReal = fixture_acopiador($zona, fixture_next_dni());
    $acopiadorSuplantado = fixture_acopiador($zona, fixture_next_dni());

    $response = $this->actingAs($acopiadorReal->user)->post('/_test/entregas-actor-seguro', [
        'metodo_identificacion' => 'DNI',
        'identificador' => $proveedor->dni,
        'litros' => 12.5,
        // Un cliente malicioso intenta hacerse pasar por otro acopiador.
        'acopiador_user_id' => $acopiadorSuplantado->user_id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('entregas', [
        'proveedor_id' => $proveedor->id,
        'acopiador_id' => $acopiadorReal->id,
        'registrado_por_user_id' => $acopiadorReal->user_id,
    ]);
    $this->assertDatabaseMissing('entregas', [
        'acopiador_id' => $acopiadorSuplantado->id,
    ]);
});

it('store llama a RegistrarEntrega usando al acopiador autenticado y redirige con éxito', function () {
    Route::post('/_test/entregas', [EntregaController::class, 'store']);

    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    $response = $this->actingAs($acopiador->user)->post('/_test/entregas', [
        'metodo_identificacion' => 'DNI',
        'identificador' => $proveedor->dni,
        'litros' => 30.5,
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
    $this->assertDatabaseHas('entregas', [
        'proveedor_id' => $proveedor->id,
        'acopiador_id' => $acopiador->id,
        'estado' => 'PENDIENTE_ANALISIS',
    ]);
});

it('store aplica la Policy: un PROVEEDOR no puede registrar entregas (403)', function () {
    Route::post('/_test/entregas-403', [EntregaController::class, 'store']);

    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);

    $response = $this->actingAs($proveedor->user)->post('/_test/entregas-403', [
        'metodo_identificacion' => 'DNI',
        'identificador' => $proveedor->dni,
        'litros' => 30.5,
    ]);

    $response->assertForbidden();
});
