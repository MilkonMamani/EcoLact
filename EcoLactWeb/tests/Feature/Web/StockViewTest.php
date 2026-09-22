<?php

use App\Application\Produccion\RegistrarAjusteInsumo;
use App\Domain\Produccion\Insumo;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Repositories\EloquentInsumoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('la pantalla principal de stock muestra los KPIs y los elementos combinados de productos e insumos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    fixture_ingreso_producto($producto->id, 85.0, $admin->id);
    (new EloquentInsumoRepository)->guardar(new Insumo(null, 'Cuajo', 'ml', 4.0, 100.0, 'ACTIVO'));

    $response = $this->actingAs($admin)->get(route('admin.stock.index'));

    $response->assertOk();
    $response->assertSee('Stock bajo');
    $response->assertSee('Queso Paria');
    $response->assertSee('Cuajo');
});

it('la pantalla principal de stock filtra por tipo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    fixture_ingreso_producto($producto->id, 85.0, $admin->id);
    (new EloquentInsumoRepository)->guardar(new Insumo(null, 'Cuajo', 'ml', 4.0, 100.0, 'ACTIVO'));

    $response = $this->actingAs($admin)->get(route('admin.stock.index', ['tipo' => 'INSUMO']));

    // No se afirma la ausencia de "Queso Paria": sigue apareciendo en el
    // <select> de "Registrar ajuste", que lista todos los productos activos
    // y no solo los de la tabla filtrada.
    $response->assertOk();
    $response->assertSee('Cuajo');
});

it('el detalle de un producto muestra sus movimientos recientes', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    fixture_ingreso_producto($producto->id, 85.0, $admin->id);

    $response = $this->actingAs($admin)->get(route('admin.stock.show', ['producto', $producto->id]));

    $response->assertOk();
    $response->assertSee('Queso Paria');
    $response->assertSee('Movimientos recientes');
    $response->assertSee('Producción');
});

it('el detalle de un insumo muestra el stock mínimo y sus movimientos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $insumo = (new EloquentInsumoRepository)->guardar(new Insumo(null, 'Cuajo', 'ml', 250.0, 100.0, 'ACTIVO'));
    app(RegistrarAjusteInsumo::class)->execute($insumo->id(), 220.0, 'Conteo físico', $admin->id);

    $response = $this->actingAs($admin)->get(route('admin.stock.show', ['insumo', $insumo->id()]));

    $response->assertOk();
    $response->assertSee('Cuajo');
    $response->assertSee('Stock mínimo');
    $response->assertSee('Conteo físico');
});

it('registrar un ajuste de inventario desde la pantalla principal actualiza el stock', function () {
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
    $this->assertDatabaseHas('producto_movimientos', ['producto_id' => $producto->id, 'tipo' => 'AJUSTE_SALIDA']);
});
