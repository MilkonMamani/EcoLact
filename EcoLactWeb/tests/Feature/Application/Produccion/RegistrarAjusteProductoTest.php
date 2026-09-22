<?php

use App\Application\Produccion\RegistrarAjusteProducto;
use App\Domain\Produccion\ProductoStockRepository;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\ProductoMovimientoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function rap_crearProducto(): ProductoModel
{
    return ProductoModel::create(['nombre' => 'Queso Paria '.uniqid(), 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
}

it('ajusta el stock hacia abajo y registra el movimiento AJUSTE', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = rap_crearProducto();
    fixture_ingreso_producto($producto->id, 85.0, $admin->id);

    $movimiento = app(RegistrarAjusteProducto::class)->execute(
        productoId: $producto->id,
        nuevoStock: 82.0,
        motivo: 'Diferencia encontrada en inventario',
        registradoPorUserId: $admin->id,
    );

    expect($movimiento->tipo())->toBe('AJUSTE_SALIDA');
    expect($movimiento->cantidad())->toBe(3.0);
    expect($movimiento->observacion())->toBe('Diferencia encontrada en inventario');

    expect(app(ProductoStockRepository::class)->buscarPorProductoId($producto->id)->cantidadActual())->toBe(82.0);
    expect(ProductoMovimientoModel::where('producto_id', $producto->id)->where('tipo', 'AJUSTE_SALIDA')->exists())->toBeTrue();
});

it('ajusta el stock hacia arriba cuando el producto todavía no tiene stock registrado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = rap_crearProducto();

    $movimiento = app(RegistrarAjusteProducto::class)->execute(
        productoId: $producto->id,
        nuevoStock: 10.0,
        motivo: 'Conteo inicial',
        registradoPorUserId: $admin->id,
    );

    expect($movimiento->cantidad())->toBe(10.0);
    expect($movimiento->tipo())->toBe('AJUSTE_ENTRADA');
    expect(app(ProductoStockRepository::class)->buscarPorProductoId($producto->id)->cantidadActual())->toBe(10.0);
});

it('rechaza un ajuste con stock negativo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = rap_crearProducto();

    app(RegistrarAjusteProducto::class)->execute(
        productoId: $producto->id,
        nuevoStock: -1.0,
        motivo: 'x',
        registradoPorUserId: $admin->id,
    );
})->throws(DomainException::class, 'El nuevo stock no puede ser negativo.');

it('rechaza un ajuste que no cambia el stock actual', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = rap_crearProducto();
    fixture_ingreso_producto($producto->id, 50.0, $admin->id);

    app(RegistrarAjusteProducto::class)->execute(
        productoId: $producto->id,
        nuevoStock: 50.0,
        motivo: 'x',
        registradoPorUserId: $admin->id,
    );
})->throws(DomainException::class, 'El nuevo stock es igual al stock actual: no hay nada que ajustar.');

it('rechaza el ajuste de un producto que no existe', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    app(RegistrarAjusteProducto::class)->execute(
        productoId: 999999,
        nuevoStock: 5.0,
        motivo: 'x',
        registradoPorUserId: $admin->id,
    );
})->throws(DomainException::class, 'El producto indicado no existe.');
