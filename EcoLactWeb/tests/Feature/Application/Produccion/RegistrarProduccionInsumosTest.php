<?php

use App\Application\Produccion\IniciarProduccion;
use App\Application\Produccion\ProduccionBloqueadaException;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use App\Infrastructure\Persistence\Models\LoteProduccionInsumoModel;
use App\Infrastructure\Persistence\Models\NotificacionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/**
 * Consumo de insumos al INICIAR una producción (antes esto lo hacía
 * RegistrarProduccion con cantidades escritas a mano; ahora salen de la receta).
 */
it('descuenta el stock del insumo y registra cuánto se usó en el lote', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $cuajo = fixture_insumo('Cuajo', 'ml', 100.0, 2.0, $admin->id);
    $producto = fixture_producto('Queso Test');
    fixture_receta($producto->id, [['insumo_id' => $cuajo->id(), 'cantidad' => 3.0, 'unidad' => 'ml']], $admin->id);

    $lote = app(IniciarProduccion::class)->execute([['producto_id' => $producto->id, 'cantidad' => 3]], $admin->id);

    expect((float) InsumoModel::find($cuajo->id())->stock_actual)->toBe(91.0);
    expect((float) LoteProduccionInsumoModel::where('lote_produccion_id', $lote->id())->where('insumo_id', $cuajo->id())->value('cantidad_usada'))->toBe(9.0);
    expect(InsumoMovimientoModel::where('insumo_id', $cuajo->id())->where('tipo', 'SALIDA')->where('motivo', 'CONSUMO_PRODUCCION')->where('lote_produccion_id', $lote->id())->exists())->toBeTrue();
});

it('notifica a los administradores activos cuando el insumo queda bajo el stock mínimo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $sal = fixture_insumo('Sal', 'kg', 10.0, 5.0, $admin->id);
    $producto = fixture_producto('Queso Test 2');
    fixture_receta($producto->id, [['insumo_id' => $sal->id(), 'cantidad' => 1.0, 'unidad' => 'kg']], $admin->id);

    app(IniciarProduccion::class)->execute([['producto_id' => $producto->id, 'cantidad' => 8]], $admin->id);

    expect(NotificacionModel::where('user_id', $admin->id)->where('tipo', 'INSUMO_STOCK_BAJO')->exists())->toBeTrue();
});

it('no permite consumir más insumo del que hay en stock', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $cloruro = fixture_insumo('Cloruro de calcio', 'ml', 5.0, 1.0, $admin->id);
    $producto = fixture_producto('Queso Test 3');
    fixture_receta($producto->id, [['insumo_id' => $cloruro->id(), 'cantidad' => 333.0, 'unidad' => 'ml']], $admin->id);

    app(IniciarProduccion::class)->execute([['producto_id' => $producto->id, 'cantidad' => 3]], $admin->id);
})->throws(ProduccionBloqueadaException::class, 'No hay suficiente Cloruro de calcio');
