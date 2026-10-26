<?php

use App\Infrastructure\Persistence\Models\CategoriaProductoModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('el combobox de Nueva receta muestra las categorías de la base de datos y al final "+ Nueva categoría"', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_categoria_id('Manjares');

    $html = $this->actingAs($admin)->get(route('admin.recetas.index'))->assertOk();

    $html->assertSeeInOrder(['name="categoria_id"', 'Quesos', 'Yogures', 'Mantequillas', 'Manjares', '+ Nueva categoría'], false);
    $html->assertSee(route('admin.recetas.categorias.store'), false);
});

it('crea una categoría desde el modal y responde su id para seleccionarla', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->postJson(route('admin.recetas.categorias.store'), ['nombre' => '  Manjares   de leche '])
        ->assertCreated()
        ->assertJson(['nombre' => 'Manjares de leche']);

    $this->assertDatabaseHas('categorias_productos', ['nombre' => 'Manjares de leche', 'nombre_normalizado' => 'manjares de leche', 'estado' => 'ACTIVA']);
});

it('no permite categorías vacías ni duplicadas aunque cambien mayúsculas o espacios', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $antes = CategoriaProductoModel::count();

    $this->actingAs($admin)->postJson(route('admin.recetas.categorias.store'), ['nombre' => '   '])
        ->assertUnprocessable()->assertJsonValidationErrors(['nombre' => 'Escriba el nombre de la categoría.']);
    $this->actingAs($admin)->postJson(route('admin.recetas.categorias.store'), ['nombre' => ' QUESOS '])
        ->assertUnprocessable()->assertJsonValidationErrors(['nombre' => 'Ya existe la categoría QUESOS.']);

    expect(CategoriaProductoModel::count())->toBe($antes);
});

it('solo quien crea recetas puede crear categorías', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');

    $this->actingAs($encargado)->postJson(route('admin.recetas.categorias.store'), ['nombre' => 'Manjares'])->assertForbidden();
    $this->assertDatabaseMissing('categorias_productos', ['nombre' => 'Manjares']);
});

it('la receta nueva liga su producto a la categoría creada desde el combobox', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $categoriaId = $this->actingAs($admin)->postJson(route('admin.recetas.categorias.store'), ['nombre' => 'Manjares'])->json('id');

    $this->actingAs($admin)->post(route('admin.recetas.store'), [
        'categoria_id' => $categoriaId,
        'nombre_producto' => 'Manjar Blanco',
        'presentacion_cantidad' => 500,
        'presentacion_unidad' => 'g',
        'ingredientes' => [['insumo_id' => fixture_leche()->id, 'cantidad' => 2, 'unidad' => 'L']],
    ])->assertSessionDoesntHaveErrors();

    $producto = ProductoModel::where('nombre', 'Manjar Blanco 500 g')->firstOrFail();
    expect($producto->categoria_id)->toBe($categoriaId);
    expect($producto->categoria->nombre)->toBe('Manjares');
    $this->assertDatabaseCount('producto_movimientos', 0);
});

it('rechaza la receta sin categoría válida', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $inactiva = fixture_categoria_id('Antigua');
    CategoriaProductoModel::whereKey($inactiva)->update(['estado' => 'INACTIVA']);

    foreach (['__nueva__', 999999, $inactiva] as $valor) {
        $this->actingAs($admin)->post(route('admin.recetas.store'), [
            'categoria_id' => $valor,
            'nombre_producto' => 'Queso X',
            'presentacion_cantidad' => 1,
            'presentacion_unidad' => 'kg',
            'ingredientes' => [['insumo_id' => fixture_leche()->id, 'cantidad' => 10, 'unidad' => 'L']],
        ])->assertSessionHasErrors('categoria_id');
    }

    expect(ProductoModel::where('nombre_base', 'Queso X')->exists())->toBeFalse();
});

it('la migración pasa los tipo_producto existentes a categorías sin perder productos', function () {
    $migracion = require database_path('migrations/2026_10_27_100000_create_categorias_productos_table.php');
    $migracion->down();

    $queso = fixture_producto('Queso Paria 1 kg');
    $yogurt = fixture_producto('Yogurt Fresa 1 L');
    $otro = fixture_producto('Manjar 1 kg');
    $historico = fixture_producto('Queso antiguo');
    DB::table('productos')->where('id', $queso->id)->update(['tipo_producto' => 'Queso']);
    DB::table('productos')->where('id', $yogurt->id)->update(['tipo_producto' => 'yogurt']);
    DB::table('productos')->where('id', $otro->id)->update(['tipo_producto' => ' Manjar  Blanco ']);
    $productos = DB::table('productos')->count();

    $migracion->up();

    expect(DB::table('productos')->count())->toBe($productos);
    expect(Schema::hasColumn('productos', 'tipo_producto'))->toBeTrue();
    expect(CategoriaProductoModel::pluck('nombre')->all())->toBe(['Quesos', 'Yogures', 'Mantequillas', 'Manjar Blanco']);
    expect(ProductoModel::find($queso->id)->categoria->nombre)->toBe('Quesos');
    expect(ProductoModel::find($yogurt->id)->categoria->nombre)->toBe('Yogures');
    expect(ProductoModel::find($otro->id)->categoria->nombre)->toBe('Manjar Blanco');
    expect(ProductoModel::find($historico->id)->categoria_id)->toBeNull();
    expect(ProductoModel::find($queso->id)->tipo_producto)->toBe('Queso');
});
