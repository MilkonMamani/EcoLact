<?php

use App\Domain\Produccion\ProductoStockRepository;
use App\Http\Controllers\Web\Produccion\StockController;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('index delega en ObtenerStockGeneral y expone el listado unificado a la vista', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    fixture_ingreso_producto($producto->id, 85.0, $admin->id);

    registrar_vistas_stub();
    $this->actingAs($admin);

    $view = app(StockController::class)->index();

    expect($view->name())->toBe('admin.stock.index');
    // Producto + el insumo del sistema LECHE, que siempre existe en el inventario.
    expect($view->getData()['items'])->toHaveCount(2);
});

it('un ENCARGADO_PLANTA no puede ver el stock unificado (solo VENTAS/ADMINISTRADOR)', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');

    registrar_vistas_stub();
    $this->actingAs($encargado);

    app(StockController::class)->index();
})->throws(AuthorizationException::class);

it('show delega en ObtenerDetalleStock para un producto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    fixture_ingreso_producto($producto->id, 85.0, $admin->id);

    registrar_vistas_stub();
    $this->actingAs($admin);

    $view = app(StockController::class)->show('producto', $producto->id);

    expect($view->name())->toBe('admin.stock.show');
    expect($view->getData()['detalle']->nombre)->toBe('Queso Paria');
});

it('show responde 404 cuando el elemento no existe', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin);

    $response = $this->get(route('admin.stock.show', ['producto', 999999]));

    $response->assertNotFound();
});

it('ajustar delega en RegistrarAjusteProducto y redirige con éxito', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    fixture_ingreso_producto($producto->id, 85.0, $admin->id);

    $response = $this->actingAs($admin)->post(route('admin.stock.ajuste'), [
        'tipo' => 'PRODUCTO',
        'elemento_id' => $producto->id,
        'nuevo_stock' => 82,
        'motivo' => 'Diferencia encontrada en inventario',
    ]);

    $response->assertRedirect(route('admin.stock.index'));
    expect(app(ProductoStockRepository::class)->buscarPorProductoId($producto->id)->cantidadActual())->toBe(82.0);
});
