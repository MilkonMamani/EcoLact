<?php

use App\Domain\Produccion\ProductoStock;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\ProductoMovimientoModel;
use App\Infrastructure\Persistence\Repositories\EloquentProductoStockRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('calcula el saldo de cada producto sumando sus movimientos con signo', function () {
    $repository = new EloquentProductoStockRepository;
    $admin = fixture_usuario('ADMINISTRADOR');

    $productoA = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    $productoB = ProductoModel::create(['nombre' => 'Yogurt', 'unidad_medida' => 'l', 'estado' => 'ACTIVO']);
    $productoSin = ProductoModel::create(['nombre' => 'Sin movimientos', 'unidad_medida' => 'l', 'estado' => 'ACTIVO']);

    fixture_ingreso_producto($productoA->id, 100.0, $admin->id);
    foreach ([['VENTA', 15.0], ['AJUSTE_SALIDA', 0.0], ['AJUSTE_ENTRADA', 5.0]] as [$tipo, $cantidad]) {
        if ($cantidad > 0) {
            ProductoMovimientoModel::create([
                'producto_id' => $productoA->id, 'tipo' => $tipo, 'cantidad' => $cantidad,
                'fecha' => now(), 'registrado_por_user_id' => $admin->id,
            ]);
        }
    }
    fixture_ingreso_producto($productoB->id, 120.0, $admin->id);

    $todos = $repository->listarTodos();

    expect($todos)->toHaveCount(2);
    $cantidadesPorProducto = collect($todos)->mapWithKeys(fn (ProductoStock $s) => [$s->productoId() => $s->cantidadActual()])->all();
    expect($cantidadesPorProducto[$productoA->id])->toBe(90.0);
    expect($cantidadesPorProducto[$productoB->id])->toBe(120.0);
    expect($repository->buscarPorProductoId($productoSin->id))->toBeNull();
});

it('ya no existe una tabla producto_stock: el stock solo depende de los movimientos', function () {
    expect(Schema::hasTable('producto_stock'))->toBeFalse();
});
