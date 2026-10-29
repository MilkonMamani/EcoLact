<?php

use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use App\Infrastructure\Persistence\Models\PlanificacionProduccionModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

// Estas pruebas no tratan de asistencia: el personal ya tiene su jornada PRESENTE de hoy.
beforeEach(fn () => fixture_personal_con_asistencia_hoy());

/**
 * @return array{0: ProductoModel, 1: ProductoModel}
 */
function escenarioPlanificacionWeb(int $userId, float $leche = 100.0): array
{
    fixture_stock_leche($leche, $userId);
    $brie = fixture_producto('Queso Brie');
    $fresco = fixture_producto('Queso fresco');
    fixture_receta($brie->id, [['insumo_id' => fixture_leche()->id, 'cantidad' => 4.0, 'unidad' => 'L']], $userId);
    fixture_receta($fresco->id, [['insumo_id' => fixture_leche()->id, 'cantidad' => 2.0, 'unidad' => 'L']], $userId);

    return [$brie, $fresco];
}

it('el administrador planifica varios productos: crea un lote por producto y vuelve al historial', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$brie, $fresco] = escenarioPlanificacionWeb($admin->id);

    $previa = $this->actingAs($admin)->postJson(route('admin.produccion.requerimientos'), [
        'productos' => [['producto_id' => $brie->id, 'cantidad' => 5], ['producto_id' => $fresco->id, 'cantidad' => 10]],
    ])->assertOk();
    $previa->assertJsonPath('data.insumos.0.necesario', 40);
    $previa->assertJsonCount(2, 'data.lineas');

    $response = $this->actingAs($admin)->post(route('admin.produccion.store'), [
        'productos' => [['producto_id' => $brie->id, 'cantidad' => 5], ['producto_id' => $fresco->id, 'cantidad' => 10]],
    ]);

    $response->assertRedirect(route('admin.produccion.index'));
    $response->assertSessionHas('success', fn (string $mensaje) => str_contains($mensaje, '2 lotes'));
    expect(LoteProduccionModel::count())->toBe(2);
    expect(PlanificacionProduccionModel::count())->toBe(1);
    expect((float) fixture_leche()->fresh()->stock_actual)->toBe(60.0);

    $lote = LoteProduccionModel::orderBy('id')->firstOrFail();
    $detalle = $this->actingAs($admin)->get(route('admin.produccion.show', $lote->id))->assertOk();
    $detalle->assertSee('Planificación PLN-');
    $detalle->assertSee('Queso fresco');
});

it('el encargado de planta también planifica varios productos en una sola operación', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    [$brie, $fresco] = escenarioPlanificacionWeb($admin->id);

    $this->actingAs($encargado)->post(route('planta.produccion.store'), [
        'productos' => [['producto_id' => $brie->id, 'cantidad' => 1], ['producto_id' => $fresco->id, 'cantidad' => 1]],
    ])->assertRedirect(route('planta.produccion.index'));

    expect(LoteProduccionModel::where('registrado_por_user_id', $encargado->id)->count())->toBe(2);
});

it('si el total no alcanza, el envío se rechaza con el insumo faltante y no se crea ningún lote', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$brie, $fresco] = escenarioPlanificacionWeb($admin->id, 30.0);

    $response = $this->actingAs($admin)->from(route('admin.produccion.index'))->post(route('admin.produccion.store'), [
        'productos' => [['producto_id' => $brie->id, 'cantidad' => 5], ['producto_id' => $fresco->id, 'cantidad' => 10]],
    ]);

    $response->assertRedirect(route('admin.produccion.index'));
    $response->assertSessionHas('error', fn (string $mensaje) => str_contains($mensaje, 'No hay suficiente leche'));
    expect(LoteProduccionModel::count())->toBe(0);
});

it('rechaza cantidades cero o negativas en cualquier fila', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$brie, $fresco] = escenarioPlanificacionWeb($admin->id);

    $this->actingAs($admin)->post(route('admin.produccion.store'), [
        'productos' => [['producto_id' => $brie->id, 'cantidad' => 5], ['producto_id' => $fresco->id, 'cantidad' => 0]],
    ])->assertSessionHasErrors('productos.1.cantidad');

    expect(LoteProduccionModel::count())->toBe(0);
});

it('un usuario de VENTAS no puede iniciar una planificación', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventas = fixture_usuario('VENTAS');
    [$brie] = escenarioPlanificacionWeb($admin->id);

    $this->actingAs($ventas)->post(route('admin.produccion.store'), [
        'productos' => [['producto_id' => $brie->id, 'cantidad' => 1]],
    ])->assertForbidden();
    $this->actingAs($ventas)->post(route('planta.produccion.store'), [
        'productos' => [['producto_id' => $brie->id, 'cantidad' => 1]],
    ])->assertForbidden();

    expect(LoteProduccionModel::count())->toBe(0);
});
