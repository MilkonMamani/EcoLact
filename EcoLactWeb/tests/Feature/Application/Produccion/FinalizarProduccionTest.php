<?php

use App\Application\Produccion\FinalizarProduccion;
use App\Application\Produccion\IniciarProduccion;
use App\Application\Ventas\RegistrarProductoPrecio;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Domain\Produccion\ProductoStockRepository;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use App\Infrastructure\Persistence\Models\ProduccionDetalleModel;
use App\Infrastructure\Persistence\Models\ProductoMovimientoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function loteEnProceso(int $userId, float $queso = 100, float $yogur = 50): array
{
    fixture_stock_leche(2000.0, $userId);
    $productoQueso = fixture_producto('Queso Paria 1 kg');
    $productoYogur = fixture_producto('Yogurt Fresa 1 L');
    fixture_receta($productoQueso->id, [['insumo_id' => fixture_leche()->id, 'cantidad' => 10.0, 'unidad' => 'L']], $userId);
    fixture_receta($productoYogur->id, [['insumo_id' => fixture_leche()->id, 'cantidad' => 1.0, 'unidad' => 'L']], $userId);

    $lote = app(IniciarProduccion::class)->execute([
        ['producto_id' => $productoQueso->id, 'cantidad' => $queso],
        ['producto_id' => $productoYogur->id, 'cantidad' => $yogur],
    ], $userId);

    $detalles = ProduccionDetalleModel::where('lote_produccion_id', $lote->id())->orderBy('id')->get();

    return [$lote, $productoQueso, $productoYogur, $detalles];
}

it('finalizar registra lo realmente obtenido (no asume planificado = producido) e ingresa los productos al inventario', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$lote, $queso, $yogur, $detalles] = loteEnProceso($admin->id);

    $finalizado = app(FinalizarProduccion::class)->execute($lote->id(), [
        $detalles[0]->id => 98,
        $detalles[1]->id => 49,
    ], $admin->id);

    expect($finalizado->estado())->toBe('FINALIZADO');
    expect($finalizado->fechaHoraFin())->not->toBeNull();

    $detalles = $detalles->fresh();
    expect((float) $detalles[0]->cantidad_planificada)->toBe(100.0);
    expect((float) $detalles[0]->cantidad_obtenida)->toBe(98.0);
    expect((float) $detalles[1]->cantidad_obtenida)->toBe(49.0);

    $stock = app(ProductoStockRepository::class);
    expect($stock->buscarPorProductoId($queso->id)->cantidadActual())->toBe(98.0);
    expect($stock->buscarPorProductoId($yogur->id)->cantidadActual())->toBe(49.0);

    $movimiento = ProductoMovimientoModel::where('producto_id', $queso->id)->firstOrFail();
    expect($movimiento->tipo)->toBe('INGRESO_PRODUCCION');
    expect($movimiento->lote_produccion_id)->toBe($lote->id());
});

it('finalizar NO vuelve a descontar insumos (sin doble descuento)', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$lote, , , $detalles] = loteEnProceso($admin->id);

    $stockLeche = (float) InsumoModel::where('codigo', 'LECHE')->value('stock_actual');
    $movimientosInsumo = InsumoMovimientoModel::count();
    expect($stockLeche)->toBe(950.0);

    app(FinalizarProduccion::class)->execute($lote->id(), [$detalles[0]->id => 100, $detalles[1]->id => 50], $admin->id);

    expect((float) InsumoModel::where('codigo', 'LECHE')->value('stock_actual'))->toBe(950.0);
    expect(InsumoMovimientoModel::count())->toBe($movimientosInsumo);
});

it('un lote finalizado no se puede finalizar otra vez ni ingresar dos veces el producto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$lote, $queso, , $detalles] = loteEnProceso($admin->id);
    $cantidades = [$detalles[0]->id => 100, $detalles[1]->id => 50];

    app(FinalizarProduccion::class)->execute($lote->id(), $cantidades, $admin->id);

    expect(fn () => app(FinalizarProduccion::class)->execute($lote->id(), $cantidades, $admin->id))
        ->toThrow(DomainException::class, 'ya fue finalizado');

    expect(ProductoMovimientoModel::where('producto_id', $queso->id)->count())->toBe(1);
});

it('exige la cantidad real de cada producto y no acepta negativos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$lote, $queso, , $detalles] = loteEnProceso($admin->id);

    expect(fn () => app(FinalizarProduccion::class)->execute($lote->id(), [$detalles[0]->id => 98], $admin->id))
        ->toThrow(DomainException::class, 'cantidad realmente obtenida');
    expect(fn () => app(FinalizarProduccion::class)->execute($lote->id(), [$detalles[0]->id => -1, $detalles[1]->id => 5], $admin->id))
        ->toThrow(DomainException::class, 'negativa');

    // Ninguno de los intentos fallidos dejó el lote a medias.
    $this->assertDatabaseHas('lotes_produccion', ['id' => $lote->id(), 'estado' => 'EN_PROCESO']);
    expect(ProductoMovimientoModel::count())->toBe(0);
    expect(ProduccionDetalleModel::whereNotNull('cantidad_obtenida')->count())->toBe(0);
});

it('una tanda que salió mal (0 obtenido) finaliza sin ingresar producto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$lote, $queso, $yogur, $detalles] = loteEnProceso($admin->id);

    app(FinalizarProduccion::class)->execute($lote->id(), [$detalles[0]->id => 0, $detalles[1]->id => 10], $admin->id);

    expect(ProductoMovimientoModel::where('producto_id', $queso->id)->count())->toBe(0);
    expect(app(ProductoStockRepository::class)->buscarPorProductoId($yogur->id)->cantidadActual())->toBe(10.0);
});

it('lo producido queda disponible para vender y la venta descuenta del mismo saldo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$lote, $queso, , $detalles] = loteEnProceso($admin->id);
    app(RegistrarProductoPrecio::class)->execute($queso->id, 'PUBLICO', 30.0, $admin->id);

    app(FinalizarProduccion::class)->execute($lote->id(), [$detalles[0]->id => 98, $detalles[1]->id => 49], $admin->id);
    app(RegistrarVentaProducto::class)->execute($queso->id, 'PUBLICO', 8.0, 'CONTADO', $admin->id);

    expect(app(ProductoStockRepository::class)->buscarPorProductoId($queso->id)->cantidadActual())->toBe(90.0);

    expect(fn () => app(RegistrarVentaProducto::class)->execute($queso->id, 'PUBLICO', 91.0, 'CONTADO', $admin->id))
        ->toThrow(DomainException::class, 'No hay stock suficiente');
});
