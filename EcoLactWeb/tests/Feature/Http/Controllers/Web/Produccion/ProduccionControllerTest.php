<?php

use App\Http\Controllers\Web\Produccion\ProduccionController;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

require_once __DIR__.'/../../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('index delega en LotesProduccionListadoQuery y expone el listado y el stock de leche a la vista', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $lote = fixture_lote(30.0, $admin->id);

    registrar_vistas_stub();
    $this->actingAs($admin);

    $view = app(ProduccionController::class)->index(Request::create('/produccion'));

    expect($view->name())->toBe('admin.produccion.index');
    expect(collect($view->getData()['datos']->filas)->contains(fn (array $fila) => $fila['id'] === $lote->id()))->toBeTrue();
    expect($view->getData()['datos']->kpis['enProceso'])->toBe(1);
    expect($view->getData()['leche']->esLeche())->toBeTrue();
});

it('show delega en ObtenerDetalleLoteProduccion y expone el detalle a la vista', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $lote = fixture_lote(30.0, $admin->id);

    registrar_vistas_stub();
    $this->actingAs($admin);

    $view = app(ProduccionController::class)->show(LoteProduccionModel::find($lote->id()));

    expect($view->name())->toBe('admin.produccion.show');
    expect($view->getData()['detalle']->lote->id())->toBe($lote->id());
    expect($view->getData()['detalle']->lecheUtilizada)->toBe(30.0);
    expect($view->getData()['puedeFinalizar'])->toBeTrue();
});

it('store ignora un registrado_por_user_id suplantado y usa siempre al administrador autenticado (actor seguro)', function () {
    Route::post('/_test/produccion-actor-seguro', [ProduccionController::class, 'store']);

    $admin = fixture_usuario('ADMINISTRADOR');
    $otroAdmin = fixture_usuario('ADMINISTRADOR');
    $sal = fixture_insumo('Sal', 'g', 1000.0, 0.0, $admin->id);
    $producto = fixture_producto('Queso Actor');
    fixture_receta($producto->id, [['insumo_id' => $sal->id(), 'cantidad' => 20.0, 'unidad' => 'g']], $admin->id);

    $response = $this->actingAs($admin)->post('/_test/produccion-actor-seguro', [
        'productos' => [['producto_id' => $producto->id, 'cantidad' => 10]],
        // Un cliente malicioso intenta hacer figurar a otro administrador
        // como quien registró el lote.
        'registrado_por_user_id' => $otroAdmin->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('lotes_produccion', ['registrado_por_user_id' => $admin->id, 'estado' => 'EN_PROCESO']);
    $this->assertDatabaseMissing('lotes_produccion', ['registrado_por_user_id' => $otroAdmin->id]);
});

it('store aplica la Policy: un PROVEEDOR no puede iniciar producciones (403)', function () {
    Route::post('/_test/produccion-403', [ProduccionController::class, 'store']);

    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $producto = fixture_producto('Queso 403');

    $response = $this->actingAs($proveedor->user)->post('/_test/produccion-403', [
        'productos' => [['producto_id' => $producto->id, 'cantidad' => 1]],
    ]);

    $response->assertForbidden();
});
