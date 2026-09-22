<?php

use App\Application\Produccion\ObtenerDetalleStock;
use App\Application\Produccion\RegistrarAjusteInsumo;
use App\Application\Ventas\RegistrarProductoPrecio;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Domain\Produccion\Insumo;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Repositories\EloquentInsumoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(RefreshDatabase::class, TestCase::class);

it('arma el detalle de un producto con sus movimientos recientes ordenados por fecha descendente', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);

    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PUBLICO', 20.0, $admin->id);
    fixture_ingreso_producto($producto->id, 100.0, $admin->id);
    app(RegistrarVentaProducto::class)->execute(
        productoId: $producto->id,
        tipoCliente: 'PUBLICO',
        cantidad: 20.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $admin->id,
    );

    $detalle = app(ObtenerDetalleStock::class)->execute('PRODUCTO', $producto->id);

    expect($detalle)->not->toBeNull();
    expect($detalle->tipo)->toBe('PRODUCTO');
    expect($detalle->cantidadActual)->toBe(80.0);
    expect($detalle->stockMinimo)->toBeNull();
    expect($detalle->movimientos)->toHaveCount(2);
    expect($detalle->movimientos[0]['tipo'])->toBe('VENTA');
    expect($detalle->movimientos[0]['origen'])->toBe('Venta');
    expect($detalle->movimientos[1]['tipo'])->toBe('INGRESO_PRODUCCION');
});

it('arma el detalle de un insumo con su stock mínimo y movimientos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $insumo = (new EloquentInsumoRepository)->guardar(new Insumo(null, 'Cuajo', 'ml', 250.0, 100.0, 'ACTIVO'));

    app(RegistrarAjusteInsumo::class)->execute($insumo->id(), 220.0, 'Conteo físico', $admin->id);

    $detalle = app(ObtenerDetalleStock::class)->execute('INSUMO', $insumo->id());

    expect($detalle->tipo)->toBe('INSUMO');
    expect($detalle->cantidadActual)->toBe(220.0);
    expect($detalle->stockMinimo)->toBe(100.0);
    expect($detalle->movimientos)->toHaveCount(1);
    expect($detalle->movimientos[0]['origen'])->toBe('Conteo físico');
});

it('devuelve null para un tipo o id inexistente', function () {
    expect(app(ObtenerDetalleStock::class)->execute('PRODUCTO', 999999))->toBeNull();
    expect(app(ObtenerDetalleStock::class)->execute('OTRO', 1))->toBeNull();
});
