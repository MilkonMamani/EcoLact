<?php

use App\Application\Produccion\IniciarProduccion;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\RecetaModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function datosDeReceta(array $extra = []): array
{
    return array_replace_recursive([
        'tipo_producto' => 'Queso',
        'nombre_producto' => 'Queso Paria',
        'presentacion_cantidad' => 1,
        'presentacion_unidad' => 'kg',
        'descripcion' => 'Fórmula base',
        'ingredientes' => [
            ['insumo_id' => fixture_leche()->id, 'cantidad' => 10, 'unidad' => 'L', 'observacion' => 'Materia prima principal'],
            ['insumo_id' => (InsumoModel::firstWhere('nombre', 'Cuajo')?->id) ?? fixture_insumo('Cuajo', 'ml', 100.0)->id(), 'cantidad' => 5, 'unidad' => 'ml', 'observacion' => 'Coagulante'],
        ],
    ], $extra);
}

/** Datos para editar una receta existente (no incluyen datos del producto). */
function datosDeEdicion(array $extra = []): array
{
    $datos = datosDeReceta($extra);
    unset($datos['tipo_producto'], $datos['nombre_producto'], $datos['presentacion_cantidad'], $datos['presentacion_unidad']);

    return $datos;
}

it('el administrador crea una receta desde Recetas y se genera solo el producto y el nombre de la receta', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_stock_leche(300.0, $admin->id);
    $datos = datosDeReceta();
    $movimientos = InsumoMovimientoModel::count();

    $response = $this->actingAs($admin)->post(route('admin.recetas.store'), $datos);

    $response->assertRedirect(route('admin.recetas.index'));

    $producto = ProductoModel::where('nombre', 'Queso Paria 1 kg')->firstOrFail();
    expect($producto->nombre_base)->toBe('Queso Paria');
    expect($producto->tipo_producto)->toBe('Queso');
    expect((float) $producto->presentacion_cantidad)->toBe(1.0);
    expect($producto->presentacion_unidad)->toBe('kg');
    expect($producto->estado)->toBe('ACTIVO');

    $receta = RecetaModel::where('producto_id', $producto->id)->firstOrFail();
    expect($receta->nombre)->toBe('Receta Queso Paria 1 kg');
    expect($receta->estado)->toBe('ACTIVA');
    expect($receta->version)->toBe(1);
    expect($receta->detalles)->toHaveCount(2);
    expect($receta->detalles->firstWhere('insumo_id', fixture_leche()->id)->observacion)->toBe('Materia prima principal');

    // Stock inicial 0 del producto y ningún movimiento de insumos: la receta no descuenta nada.
    $this->assertDatabaseCount('producto_movimientos', 0);
    expect(InsumoMovimientoModel::count())->toBe($movimientos);
    expect((float) fixture_leche()->fresh()->stock_actual)->toBe(300.0);
});

it('no existe un campo para escribir el nombre de la receta: aunque se envíe se ignora', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->post(route('admin.recetas.store'), datosDeReceta(['nombre' => 'Nombre inventado']));

    expect(RecetaModel::firstOrFail()->nombre)->toBe('Receta Queso Paria 1 kg');
});

it('el formulario muestra los campos de la nueva receta sin nombre manual', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $html = $this->actingAs($admin)->get(route('admin.recetas.index'))->assertOk();

    $html->assertSee('Define cómo se fabrica cada producto.');
    $html->assertSee('+ Nueva receta');
    $html->assertSee('Tipo de producto');
    $html->assertSee('Nombre del producto');
    $html->assertSee('Unidad de presentación');
    $html->assertSee('Imagen del producto');
    $html->assertSee('Receta generada automáticamente');
    $html->assertSee('Guardar esta receta no descuenta insumos.', false);
    $html->assertSee('Recetas activas');
    $html->assertDontSee('Nombre de la receta');
});

it('la tabla de recetas tiene la imagen como primera columna y las columnas pedidas', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');
    $this->actingAs($admin)->post(route('admin.recetas.store'), datosDeReceta(['imagen' => UploadedFile::fake()->image('queso.png', 300, 300)]));

    $html = $this->actingAs($admin)->get(route('admin.recetas.index'))->assertOk();

    $html->assertSeeInOrder(['Imagen', 'Producto', 'Presentación', 'Receta', 'Ingredientes', 'Estado', 'Actualizada', 'Acciones']);
    $html->assertSee('Queso Paria');
    $html->assertSee('1 kg');
    $html->assertSee('Receta Queso Paria 1 kg');
    $html->assertSee('2 ingredientes');
    $html->assertSee('storage/products/queso-paria-1-kg-', false);
    $html->assertSee('Ver receta');
    $html->assertSee('Editar receta');
    $html->assertSee('Desactivar receta');
});

it('valida en el servidor: tipo, nombre, presentación, unidad, ingredientes y cantidades', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->post(route('admin.recetas.store'), [])
        ->assertSessionHasErrors(['tipo_producto', 'nombre_producto', 'presentacion_cantidad', 'presentacion_unidad', 'ingredientes']);

    $this->actingAs($admin)->post(route('admin.recetas.store'), datosDeReceta([
        'presentacion_cantidad' => 0,
        'presentacion_unidad' => 'libras',
        'ingredientes' => [['cantidad' => 0], ['insumo_id' => 99999]],
    ]))->assertSessionHasErrors(['presentacion_cantidad', 'presentacion_unidad']);

    expect(RecetaModel::count())->toBe(0);
    expect(ProductoModel::count())->toBe(0);
});

it('rechaza un ingrediente repetido y no crea el producto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->post(route('admin.recetas.store'), datosDeReceta([
        'ingredientes' => [1 => ['insumo_id' => fixture_leche()->id]],
    ]));

    $response->assertSessionHasErrors('ingredientes.1.insumo_id');
    expect(ProductoModel::count())->toBe(0);
});

it('una segunda receta con el mismo producto y presentación no crea un producto duplicado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $this->actingAs($admin)->post(route('admin.recetas.store'), datosDeReceta());

    $response = $this->actingAs($admin)->post(route('admin.recetas.store'), datosDeReceta());

    $response->assertSessionHas('error', fn (string $m) => str_contains($m, 'Ya existe el producto Queso Paria 1 kg'));
    expect(ProductoModel::where('nombre', 'Queso Paria 1 kg')->count())->toBe(1);
    expect(RecetaModel::count())->toBe(1);
});

it('la misma presentación con otra unidad sí es otro producto (Queso Paria 500 g)', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $this->actingAs($admin)->post(route('admin.recetas.store'), datosDeReceta());
    $this->actingAs($admin)->post(route('admin.recetas.store'), datosDeReceta(['presentacion_cantidad' => 500, 'presentacion_unidad' => 'g']));

    expect(ProductoModel::pluck('nombre')->sort()->values()->all())->toBe(['Queso Paria 1 kg', 'Queso Paria 500 g']);
});

it('una receta usada no se edita: al editarla se crea la versión siguiente sobre el mismo producto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_stock_leche(500.0, $admin->id);
    $this->actingAs($admin)->post(route('admin.recetas.store'), datosDeReceta());
    $producto = ProductoModel::firstOrFail();
    $v1 = RecetaModel::firstOrFail();
    app(IniciarProduccion::class)->execute([['producto_id' => $producto->id, 'cantidad' => 2]], $admin->id);

    $response = $this->actingAs($admin)->patch(route('admin.recetas.update', $v1->id), datosDeEdicion([
        'ingredientes' => [0 => ['cantidad' => 12]],
    ]));

    $response->assertSessionHas('success', fn (string $m) => str_contains($m, 'versión 2'));
    expect(RecetaModel::where('producto_id', $producto->id)->orderBy('version')->pluck('estado', 'version')->all())->toBe([1 => 'INACTIVA', 2 => 'ACTIVA']);
    expect(ProductoModel::count())->toBe(1);
    expect((float) $v1->fresh()->detalles->firstWhere('insumo_id', fixture_leche()->id)->cantidad)->toBe(10.0);
});

it('una receta sin usar se edita en el mismo lugar', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $this->actingAs($admin)->post(route('admin.recetas.store'), datosDeReceta());
    $receta = RecetaModel::firstOrFail();

    $this->actingAs($admin)->patch(route('admin.recetas.update', $receta->id), datosDeEdicion([
        'descripcion' => 'Nueva fórmula',
        'ingredientes' => [0 => ['cantidad' => 11]],
    ]))->assertSessionHas('success', 'Receta actualizada.');

    expect(RecetaModel::count())->toBe(1);
    expect($receta->fresh()->descripcion)->toBe('Nueva fórmula');
    expect((float) $receta->fresh()->detalles->firstWhere('insumo_id', fixture_leche()->id)->cantidad)->toBe(11.0);
});

it('activar y desactivar una receta desde la interfaz', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $this->actingAs($admin)->post(route('admin.recetas.store'), datosDeReceta());
    $receta = RecetaModel::firstOrFail();

    $this->actingAs($admin)->patch(route('admin.recetas.desactivar', $receta->id))->assertSessionHas('success');
    expect($receta->fresh()->estado)->toBe('INACTIVA');

    $this->actingAs($admin)->patch(route('admin.recetas.activar', $receta->id))->assertSessionHas('success');
    expect($receta->fresh()->estado)->toBe('ACTIVA');
});

it('el encargado de planta ve las recetas con su imagen en solo lectura y no puede configurarlas', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $this->actingAs($admin)->post(route('admin.recetas.store'), datosDeReceta(['imagen' => UploadedFile::fake()->image('q.jpg')]));
    $receta = RecetaModel::firstOrFail();

    $lectura = $this->actingAs($encargado)->get(route('planta.recetas.index'));
    $lectura->assertOk();
    $lectura->assertSee('Queso Paria');
    $lectura->assertSee('storage/products/', false);
    $lectura->assertDontSee('+ Nueva receta');
    $lectura->assertDontSee('Editar receta');

    $this->actingAs($encargado)->get(route('admin.recetas.index'))->assertForbidden();
    $this->actingAs($encargado)->post(route('admin.recetas.store'), datosDeReceta())->assertForbidden();
    $this->actingAs($encargado)->patch(route('admin.recetas.desactivar', $receta->id))->assertForbidden();
});

it('otros roles no acceden a las recetas', function () {
    $ventas = fixture_usuario('VENTAS');

    $this->actingAs($ventas)->get(route('planta.recetas.index'))->assertForbidden();
    $this->actingAs($ventas)->get(route('admin.recetas.index'))->assertForbidden();
});

it('la previsualización avisa cuando un producto no tiene receta', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $sinReceta = fixture_producto('Yogurt Fresa 1 L');

    $json = $this->actingAs($encargado)->postJson(route('planta.produccion.requerimientos'), [
        'productos' => [['producto_id' => $sinReceta->id, 'cantidad' => 5]],
    ]);

    $json->assertOk()
        ->assertJsonPath('data.puede_iniciar', false)
        ->assertJsonPath('data.productos_sin_receta.0.producto', 'Yogurt Fresa 1 L');
    expect($json->json('data.mensajes.0'))->toContain('todavía no tiene una receta configurada');
});

it('la pantalla de nueva producción solo ofrece productos con receta activa y muestra su imagen', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');
    $this->actingAs($admin)->post(route('admin.recetas.store'), datosDeReceta(['imagen' => UploadedFile::fake()->image('q.png')]));
    fixture_producto('Producto histórico sin receta');

    $html = $this->actingAs($admin)->get(route('admin.produccion.index'))->assertOk();

    $html->assertSee('Queso Paria 1 kg');
    $html->assertDontSee('Producto histórico sin receta');
    $html->assertSee('storage\/products\/queso-paria-1-kg-', false);
});

it('el listado de recetas se puede filtrar por producto y estado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $this->actingAs($admin)->post(route('admin.recetas.store'), datosDeReceta());
    $this->actingAs($admin)->post(route('admin.recetas.store'), datosDeReceta(['nombre_producto' => 'Yogurt Beta', 'tipo_producto' => 'Yogurt']));
    $beta = ProductoModel::where('nombre_base', 'Yogurt Beta')->firstOrFail();
    RecetaModel::where('producto_id', $beta->id)->update(['estado' => 'INACTIVA', 'producto_activo_id' => null]);
    $paria = ProductoModel::where('nombre_base', 'Queso Paria')->firstOrFail();
    $this->flushSession();

    $porProducto = $this->actingAs($admin)->get(route('admin.recetas.index', ['producto' => $paria->id]));
    $porProducto->assertSee('Receta Queso Paria 1 kg');
    $porProducto->assertDontSee('Receta Yogurt Beta 1 kg');

    $inactivas = $this->actingAs($admin)->get(route('admin.recetas.index', ['estado' => 'INACTIVA']));
    $inactivas->assertSee('Receta Yogurt Beta 1 kg');
    $inactivas->assertDontSee('Receta Queso Paria 1 kg');
});

it('un producto histórico sin receta se configura sin duplicar el producto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $historico = fixture_producto('queso paria', 'Kl');

    $this->actingAs($admin)->get(route('admin.recetas.index', ['configurar' => $historico->id]))
        ->assertOk()
        ->assertSee('data-configurar=', false);

    $this->actingAs($admin)->post(route('admin.recetas.store'), [
        'producto_existente_id' => $historico->id,
        'descripcion' => null,
        'ingredientes' => datosDeReceta()['ingredientes'],
    ])->assertSessionHas('success');

    expect(ProductoModel::count())->toBe(1);
    expect(RecetaModel::firstOrFail()->nombre)->toBe('Receta queso paria');
    expect(RecetaModel::firstOrFail()->producto_id)->toBe($historico->id);
});
