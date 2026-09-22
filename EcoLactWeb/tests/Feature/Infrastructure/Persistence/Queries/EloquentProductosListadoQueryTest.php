<?php

use App\Application\Produccion\FinalizarProduccion;
use App\Application\Produccion\IniciarProduccion;
use App\Application\Produccion\ProductosFiltro;
use App\Infrastructure\Persistence\Models\ProduccionDetalleModel;
use App\Infrastructure\Persistence\Models\RecetaModel;
use App\Infrastructure\Persistence\Queries\EloquentProductosListadoQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(RefreshDatabase::class, TestCase::class);

function eprodq_filtroVacio(): ProductosFiltro
{
    return ProductosFiltro::resolver(null, null);
}

it('calcula los KPIs y arma las filas con stock, receta y última producción', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_stock_leche(100.0, $admin->id);

    $conStock = fixture_producto('Queso Andino');
    $conStock->update(['nombre_base' => 'Queso Andino', 'tipo_producto' => 'Queso', 'presentacion_cantidad' => 500, 'presentacion_unidad' => 'g']);
    $sinStock = fixture_producto('Yogur');
    $sinReceta = fixture_producto('Histórico');

    fixture_receta($conStock->id, [['insumo_id' => fixture_leche()->id, 'cantidad' => 2.0, 'unidad' => 'L']], $admin->id);
    fixture_receta($sinStock->id, [['insumo_id' => fixture_leche()->id, 'cantidad' => 1.0, 'unidad' => 'L']], $admin->id);
    $lote = app(IniciarProduccion::class)->execute([['producto_id' => $conStock->id, 'cantidad' => 10]], $admin->id);
    app(FinalizarProduccion::class)->execute($lote->id(), [ProduccionDetalleModel::first()->id => 9], $admin->id);

    $datos = (new EloquentProductosListadoQuery)->obtener(eprodq_filtroVacio());

    expect($datos->kpis)->toBe(['total' => 3, 'disponibles' => 1, 'sinStock' => 2, 'sinReceta' => 1]);
    expect($datos->filas)->toHaveCount(3);

    $fila = collect($datos->filas)->firstWhere('nombre', 'Queso Andino');
    expect($fila['stockActual'])->toBe(9.0);
    expect($fila['presentacion'])->toBe('500 g');
    expect($fila['tipo'])->toBe('Queso');
    expect($fila['recetaEstado'])->toBe('CONFIGURADA');
    expect($fila['estado'])->toBe('DISPONIBLE');
    expect($fila['ultimaProduccion'])->toBe(now(config('app.timezone'))->format('d/m/Y'));

    $filaSin = collect($datos->filas)->firstWhere('nombre', 'Yogur');
    expect($filaSin['estado'])->toBe('SIN_STOCK');
    expect($filaSin['ultimaProduccion'])->toBeNull();
    expect(collect($datos->filas)->firstWhere('nombre', 'Histórico')['recetaEstado'])->toBe('SIN_RECETA');
});

it('distingue una receta inactiva de la ausencia de receta', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = fixture_producto('Queso');
    fixture_receta($producto->id, [['insumo_id' => fixture_leche()->id, 'cantidad' => 2.0, 'unidad' => 'L']], $admin->id);
    RecetaModel::query()->update(['estado' => 'INACTIVA', 'producto_activo_id' => null]);

    $fila = (new EloquentProductosListadoQuery)->obtener(eprodq_filtroVacio())->filas[0];

    expect($fila['recetaEstado'])->toBe('INACTIVA');
});

it('filtra por búsqueda y por estado de stock', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_producto('Queso Andino');
    $yogur = fixture_producto('Yogur');
    fixture_ingreso_producto($yogur->id, 4.0, $admin->id);

    $porBusqueda = ProductosFiltro::resolver('Andino', null);
    expect((new EloquentProductosListadoQuery)->obtener($porBusqueda)->filas)->toHaveCount(1);

    $disponibles = (new EloquentProductosListadoQuery)->obtener(ProductosFiltro::resolver(null, 'DISPONIBLE'));
    expect(collect($disponibles->filas)->pluck('nombre')->all())->toBe(['Yogur']);

    $sinStock = (new EloquentProductosListadoQuery)->obtener(ProductosFiltro::resolver(null, 'SIN_STOCK'));
    expect(collect($sinStock->filas)->pluck('nombre')->all())->toBe(['Queso Andino']);
});
