<?php

use App\Application\Produccion\ObtenerStockGeneral;
use App\Domain\Produccion\Insumo;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Repositories\EloquentInsumoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('combina productos e insumos en una sola lista, ordenada por nombre', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $producto = ProductoModel::create(['nombre' => 'Zeta Queso', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    fixture_ingreso_producto($producto->id, 85.0, $admin->id);

    (new EloquentInsumoRepository)->guardar(new Insumo(null, 'Alfa Cuajo', 'ml', 4.0, 5.0, 'ACTIVO'));

    $items = array_values(array_filter(
        app(ObtenerStockGeneral::class)->execute(),
        fn ($item) => $item->nombre() !== 'Leche',
    ));

    expect($items)->toHaveCount(2);
    expect($items[0]->nombre())->toBe('Alfa Cuajo');
    expect($items[0]->tipo())->toBe('INSUMO');
    expect($items[0]->stockBajo())->toBeTrue();
    expect($items[1]->nombre())->toBe('Zeta Queso');
    expect($items[1]->tipo())->toBe('PRODUCTO');
    expect($items[1]->cantidadActual())->toBe(85.0);
    expect($items[1]->stockMinimo())->toBeNull();
});

it('un producto sin movimientos de stock aparece con cantidad cero, no se omite', function () {
    ProductoModel::create(['nombre' => 'Producto Sin Stock', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);

    $items = array_values(array_filter(
        app(ObtenerStockGeneral::class)->execute(),
        fn ($item) => $item->nombre() !== 'Leche',
    ));

    expect($items)->toHaveCount(1);
    expect($items[0]->cantidadActual())->toBe(0.0);
    expect($items[0]->sinStock())->toBeTrue();
});
