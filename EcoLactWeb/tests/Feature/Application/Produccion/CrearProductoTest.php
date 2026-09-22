<?php

use App\Application\Produccion\CrearRecetaConProducto;
use App\Application\Produccion\ImagenSubida;
use App\Domain\Produccion\ProductoStockRepository;
use App\Domain\Produccion\Receta;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\RecetaModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/**
 * El producto ya no se crea a mano (antes CrearProducto): NACE desde su receta.
 */
function nuevaRecetaConProducto(int $userId, array $sobrescribir = []): Receta
{
    $argumentos = array_replace([
        'tipoProducto' => 'Queso',
        'nombreBase' => 'Queso Paria',
        'presentacionCantidad' => 1.0,
        'presentacionUnidad' => 'kg',
        'descripcion' => null,
        'ingredientes' => [
            ['insumo_id' => fixture_leche()->id, 'cantidad' => 10.0, 'unidad' => 'L', 'observacion' => 'Materia prima principal'],
        ],
        'creadaPorUserId' => $userId,
    ], $sobrescribir);

    return app(CrearRecetaConProducto::class)->execute(...$argumentos);
}

it('guardar una receta nueva crea su producto terminado con stock 0 y la receta ligada a él', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $receta = nuevaRecetaConProducto($admin->id);

    $producto = ProductoModel::findOrFail($receta->productoId());
    expect($producto->nombre)->toBe('Queso Paria 1 kg');
    expect($producto->nombre_base)->toBe('Queso Paria');
    expect($producto->tipo_producto)->toBe('Queso');
    expect($producto->unidad_medida)->toBe('unidad');
    expect($producto->estado)->toBe('ACTIVO');
    expect($receta->nombre())->toBe('Receta Queso Paria 1 kg');
    expect($receta->rendimientoCantidad())->toBe(1.0);
    expect($receta->rendimientoUnidad())->toBe('unidad');
    expect(app(ProductoStockRepository::class)->buscarPorProductoId($producto->id))->toBeNull(); // sin movimientos: stock 0
    $this->assertDatabaseCount('producto_movimientos', 0);
});

it('normaliza el nombre y genera "Queso Paria 500 g" con cantidades decimales', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $receta = nuevaRecetaConProducto($admin->id, ['nombreBase' => '  Queso   Andino ', 'presentacionCantidad' => 500.0, 'presentacionUnidad' => 'g']);
    $medio = nuevaRecetaConProducto($admin->id, ['nombreBase' => 'Yogurt Natural', 'tipoProducto' => 'Yogurt', 'presentacionCantidad' => 0.5, 'presentacionUnidad' => 'L']);

    expect(ProductoModel::findOrFail($receta->productoId())->nombre)->toBe('Queso Andino 500 g');
    expect(ProductoModel::findOrFail($medio->productoId())->nombre)->toBe('Yogurt Natural 0.5 L');
});

it('no crea un producto duplicado: el producto es único aunque se intente otra receta', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    nuevaRecetaConProducto($admin->id);

    expect(fn () => nuevaRecetaConProducto($admin->id))->toThrow(DomainException::class, 'Ya existe el producto Queso Paria 1 kg');

    expect(ProductoModel::count())->toBe(1);
    expect(RecetaModel::count())->toBe(1);
});

it('valida los datos del producto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    expect(fn () => nuevaRecetaConProducto($admin->id, ['nombreBase' => '  ']))->toThrow(DomainException::class, 'tipo y el nombre');
    expect(fn () => nuevaRecetaConProducto($admin->id, ['presentacionCantidad' => 0.0]))->toThrow(DomainException::class, 'mayor que cero');
    expect(fn () => nuevaRecetaConProducto($admin->id, ['presentacionUnidad' => 'libras']))->toThrow(DomainException::class, 'unidad de presentación');
    expect(ProductoModel::count())->toBe(0);
});

it('hace ROLLBACK completo si falla algo: ni producto, ni receta, ni imagen guardada', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');
    $imagen = UploadedFile::fake()->image('queso.png');

    // Un ingrediente inexistente hace fallar la creación de la receta ya con el producto guardado.
    $fallo = fn () => nuevaRecetaConProducto($admin->id, [
        'ingredientes' => [['insumo_id' => 999999, 'cantidad' => 1.0, 'unidad' => 'L']],
        'imagen' => new ImagenSubida($imagen->getRealPath(), 'png'),
    ]);

    expect($fallo)->toThrow(DomainException::class);
    expect(ProductoModel::count())->toBe(0);
    expect(RecetaModel::count())->toBe(0);
    expect(Storage::disk('public')->allFiles('products'))->toBe([]);
});

it('si falla DESPUÉS de guardar el producto se revierte todo y se borra la imagen', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');
    $imagen = UploadedFile::fake()->image('queso.png');

    // El usuario creador no existe: la receta falla por clave foránea con el producto ya insertado.
    expect(fn () => nuevaRecetaConProducto(999999, ['imagen' => new ImagenSubida($imagen->getRealPath(), 'png')]))
        ->toThrow(DomainException::class, 'No se pudo guardar la receta');

    expect(ProductoModel::count())->toBe(0);
    expect(Storage::disk('public')->allFiles('products'))->toBe([]);
});

it('crear una receta no mueve ningún insumo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_stock_leche(100.0, $admin->id);
    $antes = InsumoMovimientoModel::count();

    nuevaRecetaConProducto($admin->id);

    expect(InsumoMovimientoModel::count())->toBe($antes);
    expect((float) fixture_leche()->fresh()->stock_actual)->toBe(100.0);
});
