<?php

use App\Application\Produccion\ActivarReceta;
use App\Application\Produccion\ActualizarReceta;
use App\Application\Produccion\CrearNuevaVersionReceta;
use App\Application\Produccion\CrearReceta;
use App\Application\Produccion\DesactivarReceta;
use App\Application\Produccion\IniciarProduccion;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\RecetaDetalleModel;
use App\Infrastructure\Persistence\Models\RecetaModel;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function recetaQuesoParia(int $userId): array
{
    $cuajo = fixture_insumo('Cuajo', 'ml', 1000.0, 0.0, $userId);
    $sal = fixture_insumo('Sal', 'g', 5000.0, 0.0, $userId);
    $producto = fixture_producto('Queso Paria 1 kg');
    $receta = fixture_receta($producto->id, [
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 10.0, 'unidad' => 'L'],
        ['insumo_id' => $cuajo->id(), 'cantidad' => 5.0, 'unidad' => 'ml'],
        ['insumo_id' => $sal->id(), 'cantidad' => 20.0, 'unidad' => 'g'],
    ], $userId);

    return [$producto, $receta, $cuajo, $sal];
}

it('crea una receta con varios ingredientes, la leche incluida como un ingrediente más', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$producto, $receta] = recetaQuesoParia($admin->id);

    expect($receta->version())->toBe(1);
    expect($receta->estaActiva())->toBeTrue();
    expect($receta->detalles())->toHaveCount(3);
    expect(RecetaDetalleModel::where('receta_id', $receta->id())->where('insumo_id', fixture_leche()->id)->exists())->toBeTrue();
    expect($receta->productoId())->toBe($producto->id);
});

it('guardar, editar o activar una receta NO descuenta ningún insumo ni crea movimientos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_stock_leche(500.0, $admin->id);
    $movimientosAntes = InsumoMovimientoModel::count();
    $stockAntes = InsumoModel::orderBy('id')->pluck('stock_actual', 'id')->all();

    [, $receta] = recetaQuesoParia($admin->id);
    $movimientosAntes += 2; // stock inicial de Cuajo y Sal (movimientos de alta, no de la receta)
    $insumos = $receta->detalles();

    app(ActualizarReceta::class)->execute($receta->id(), 'Descripción', [
        ['insumo_id' => $insumos[0]->insumoId(), 'cantidad' => 20.0, 'unidad' => 'L'],
    ]);
    app(DesactivarReceta::class)->execute($receta->id());
    app(ActivarReceta::class)->execute($receta->id());

    expect(InsumoMovimientoModel::count())->toBe($movimientosAntes);
    expect((float) fixture_leche()->fresh()->stock_actual)->toBe(500.0);
    expect($stockAntes[fixture_leche()->id])->toEqual(500.0);
});

it('una receta sin usar se puede editar', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$producto, $receta, $cuajo] = recetaQuesoParia($admin->id);

    $editada = app(ActualizarReceta::class)->execute($receta->id(), 'Nueva descripción', [
        ['insumo_id' => $cuajo->id(), 'cantidad' => 8.0, 'unidad' => 'ml', 'observacion' => 'Coagulante'],
    ]);

    // El nombre NO se puede escribir: se genera con el producto.
    expect($editada->nombre())->toBe('Receta Queso Paria 1 kg');
    expect($editada->descripcion())->toBe('Nueva descripción');
    expect($editada->detalles()[0]->observacion())->toBe('Coagulante');
    expect($editada->detalles())->toHaveCount(1);
    expect($editada->version())->toBe(1);
});

it('una receta ya usada por una producción no se modifica: se crea una nueva versión y la producción histórica conserva la suya', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_stock_leche(1000.0, $admin->id);
    [$producto, $v1, $cuajo] = recetaQuesoParia($admin->id);

    $lote = app(IniciarProduccion::class)->execute([['producto_id' => $producto->id, 'cantidad' => 2]], $admin->id);

    expect(fn () => app(ActualizarReceta::class)->execute($v1->id(), null, [
        ['insumo_id' => $cuajo->id(), 'cantidad' => 1.0, 'unidad' => 'ml'],
    ]))->toThrow(DomainException::class, 'Cree una nueva versión');

    $v2 = app(CrearNuevaVersionReceta::class)->execute($v1->id(), null, [
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 12.0, 'unidad' => 'L'],
        ['insumo_id' => $cuajo->id(), 'cantidad' => 6.0, 'unidad' => 'ml'],
    ], $admin->id);

    expect($v2->version())->toBe(2);
    // El producto NO se duplica: la versión 2 apunta al mismo producto.
    expect($v2->productoId())->toBe($producto->id);
    expect(ProductoModel::where('nombre', 'like', 'Queso Paria%')->count())->toBe(1);
    expect($v2->estaActiva())->toBeTrue();
    expect(RecetaModel::find($v1->id())->estado)->toBe('INACTIVA');
    // La versión 1 conserva exactamente sus ingredientes originales.
    expect(RecetaDetalleModel::where('receta_id', $v1->id())->count())->toBe(3);
    // La producción histórica sigue apuntando a la versión que usó.
    $this->assertDatabaseHas('produccion_detalles', ['lote_produccion_id' => $lote->id(), 'receta_id' => $v1->id(), 'receta_version' => 1]);
});

it('un producto tiene como máximo una receta activa: activar una desactiva la otra', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$producto, $v1, $cuajo] = recetaQuesoParia($admin->id);

    $v2 = app(CrearNuevaVersionReceta::class)->execute($v1->id(), null, [
        ['insumo_id' => $cuajo->id(), 'cantidad' => 6.0, 'unidad' => 'ml'],
    ], $admin->id, activar: false);

    expect(RecetaModel::where('producto_id', $producto->id)->where('estado', 'ACTIVA')->count())->toBe(1);
    expect(RecetaModel::find($v1->id())->estado)->toBe('ACTIVA');

    app(ActivarReceta::class)->execute($v2->id());

    expect(RecetaModel::where('producto_id', $producto->id)->where('estado', 'ACTIVA')->pluck('id')->all())->toBe([$v2->id()]);
    expect(RecetaModel::find($v1->id())->estado)->toBe('INACTIVA');
});

it('la base de datos impide dos recetas activas del mismo producto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$producto, $v1] = recetaQuesoParia($admin->id);

    expect(fn () => RecetaModel::create([
        'producto_id' => $producto->id,
        'nombre' => 'Intrusa',
        'rendimiento_cantidad' => 1,
        'rendimiento_unidad' => 'unidad',
        'version' => 2,
        'estado' => 'ACTIVA',
        'producto_activo_id' => $producto->id,
        'created_by_user_id' => $admin->id,
    ]))->toThrow(QueryException::class);
});

it('un producto con receta no admite otra receta nueva: hay que crear una versión', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$producto] = recetaQuesoParia($admin->id);

    app(CrearReceta::class)->execute($producto->id, null, [
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 1.0, 'unidad' => 'L'],
    ], $admin->id);
})->throws(DomainException::class, 'nueva versión');

it('valida ingredientes: repetidos, unidades incompatibles, cantidades y lista vacía', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = fixture_producto('Yogurt');
    $leche = fixture_leche()->id;
    $crear = fn (array $ingredientes) => app(CrearReceta::class)->execute($producto->id, null, $ingredientes, $admin->id);

    expect(fn () => $crear([]))->toThrow(DomainException::class, 'al menos un ingrediente');
    expect(fn () => $crear([['insumo_id' => $leche, 'cantidad' => 1.0, 'unidad' => 'L'], ['insumo_id' => $leche, 'cantidad' => 2.0, 'unidad' => 'L']]))
        ->toThrow(DomainException::class, 'repetido');
    expect(fn () => $crear([['insumo_id' => $leche, 'cantidad' => 1.0, 'unidad' => 'kg']]))
        ->toThrow(DomainException::class, 'no es compatible');
    expect(fn () => $crear([['insumo_id' => $leche, 'cantidad' => 0.0, 'unidad' => 'L']]))
        ->toThrow(DomainException::class, 'mayor que cero');
});

it('acepta ingredientes en una unidad compatible distinta a la base del insumo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = fixture_producto('Yogurt');

    $receta = app(CrearReceta::class)->execute($producto->id, null, [
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 500.0, 'unidad' => 'ml'],
    ], $admin->id);

    expect($receta->detalles()[0]->unidad())->toBe('ml');
});

it('no permite activar una receta sin ingredientes', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = fixture_producto('Vacío');
    $receta = RecetaModel::create([
        'producto_id' => $producto->id, 'nombre' => 'Vacía', 'rendimiento_cantidad' => 1,
        'rendimiento_unidad' => 'unidad', 'version' => 1, 'estado' => 'INACTIVA',
        'created_by_user_id' => $admin->id,
    ]);

    app(ActivarReceta::class)->execute($receta->id);
})->throws(DomainException::class, 'sin ingredientes');
