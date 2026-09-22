<?php

use App\Application\Produccion\FinalizarProduccion;
use App\Application\Produccion\IniciarProduccion;
use App\Infrastructure\Persistence\Models\ProduccionDetalleModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function crearRecetaPorHttp($test, $admin, array $extra = []): ProductoModel
{
    $test->actingAs($admin)->post(route('admin.recetas.store'), array_replace_recursive([
        'tipo_producto' => 'Queso',
        'nombre_producto' => 'Queso Paria',
        'presentacion_cantidad' => 1,
        'presentacion_unidad' => 'kg',
        'ingredientes' => [['insumo_id' => fixture_leche()->id, 'cantidad' => 10, 'unidad' => 'L']],
    ], $extra))->assertSessionDoesntHaveErrors();

    // El mensaje de éxito quedaría visible en la siguiente página; se limpia la sesión.
    $test->flushSession();

    return ProductoModel::where('nombre_base', $extra['nombre_producto'] ?? 'Queso Paria')->firstOrFail();
}

it('el producto aparece en Productos apenas se crea su receta, con stock 0 y sin stock', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    crearRecetaPorHttp($this, $admin);

    $html = $this->actingAs($admin)->get(route('admin.productos.index'))->assertOk();

    $html->assertSee('Productos terminados generados desde las recetas y disponibles después de la producción.');
    $html->assertSee('Queso Paria');
    $html->assertSee('1 kg');
    $html->assertSee('0 unidades');
    $html->assertSee('Configurada');
    $html->assertSee('Sin stock');
});

it('Productos NO es un CRUD: no hay "+ Nuevo producto", ni acciones, ni rutas de crear o editar', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = crearRecetaPorHttp($this, $admin);

    $html = $this->actingAs($admin)->get(route('admin.productos.index'))->assertOk();

    $html->assertDontSee('Nuevo producto');
    $html->assertDontSee('Acciones');
    $html->assertDontSee('Editar');
    $html->assertDontSee('Desactivar');
    $html->assertDontSee('Eliminar');
    expect(Route::has('admin.productos.store'))->toBeFalse();
    expect(Route::has('admin.productos.update'))->toBeFalse();
    $this->actingAs($admin)->post('/admin/productos', ['nombre' => 'Manual'])->assertStatus(405);
    $this->actingAs($admin)->patch('/admin/productos/'.$producto->id, ['estado' => 'INACTIVO'])->assertStatus(405);
    expect(ProductoModel::where('nombre', 'Manual')->exists())->toBeFalse();
});

it('la tabla de productos tiene la imagen como primera columna', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');
    crearRecetaPorHttp($this, $admin, ['imagen' => UploadedFile::fake()->image('queso.png')]);

    $this->actingAs($admin)->get(route('admin.productos.index'))
        ->assertSeeInOrder(['Imagen', 'Producto', 'Presentación', 'Stock actual', 'Receta', 'Última producción', 'Estado'])
        ->assertSee('storage/products/queso-paria-1-kg-', false);
});

it('es EXACTAMENTE la misma imagen en Recetas y en Productos, guardada una sola vez', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = crearRecetaPorHttp($this, $admin, ['imagen' => UploadedFile::fake()->image('queso.webp')->mimeType('image/webp')]);

    $ruta = $producto->imagen;
    expect($ruta)->not->toBeNull();

    $this->actingAs($admin)->get(route('admin.recetas.index'))->assertSee($ruta, false);
    $this->actingAs($admin)->get(route('admin.productos.index'))->assertSee($ruta, false);
    $this->actingAs($admin)->get(route('admin.productos.show', $producto->id))->assertSee($ruta, false);
    expect(Storage::disk('public')->allFiles())->toBe([$ruta]);
});

it('valida la imagen: tipo MIME y tamaño máximo de 2 MB', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');
    $base = [
        'tipo_producto' => 'Queso', 'nombre_producto' => 'Queso Paria', 'presentacion_cantidad' => 1, 'presentacion_unidad' => 'kg',
        'ingredientes' => [['insumo_id' => fixture_leche()->id, 'cantidad' => 10, 'unidad' => 'L']],
    ];

    $this->actingAs($admin)->post(route('admin.recetas.store'), $base + ['imagen' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')])->assertSessionHasErrors('imagen');
    $this->actingAs($admin)->post(route('admin.recetas.store'), $base + ['imagen' => UploadedFile::fake()->image('gif.gif')])->assertSessionHasErrors('imagen');
    $this->actingAs($admin)->post(route('admin.recetas.store'), $base + ['imagen' => UploadedFile::fake()->image('grande.png')->size(3000)])->assertSessionHasErrors('imagen');

    expect(ProductoModel::count())->toBe(0);
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('la imagen es opcional y sin ella se muestra un placeholder', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    crearRecetaPorHttp($this, $admin);

    $this->actingAs($admin)->get(route('admin.productos.index'))->assertSee('Queso Paria 1 kg (sin imagen)', false);
    $this->actingAs($admin)->get(route('admin.recetas.index'))->assertSee('sin imagen', false);
});

it('el detalle del producto es de consulta: muestra receta, stock, historial y no permite editar', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_stock_leche(1500.0, $admin->id);
    $producto = crearRecetaPorHttp($this, $admin);
    $lote = app(IniciarProduccion::class)->execute([['producto_id' => $producto->id, 'cantidad' => 100]], $admin->id);
    app(FinalizarProduccion::class)->execute($lote->id(), [ProduccionDetalleModel::first()->id => 98], $admin->id);

    $html = $this->actingAs($admin)->get(route('admin.productos.show', $producto->id))->assertOk();

    $html->assertSee('Queso Paria');
    $html->assertSee('1 kg');
    $html->assertSee('98 unidades');
    $html->assertSee('Receta Queso Paria 1 kg');
    $html->assertSee('Ingreso por producción');
    $html->assertSee($lote->codigo());
    $html->assertSee('Ver receta');
    $html->assertDontSee('Editar');
    $html->assertDontSee('Guardar');
});

it('el stock del producto aumenta solo al FINALIZAR con la cantidad real, y pasa de Sin stock a Disponible', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_stock_leche(1500.0, $admin->id);
    $producto = crearRecetaPorHttp($this, $admin);

    $this->actingAs($admin)->get(route('admin.productos.index'))->assertSee('Sin stock');

    $lote = app(IniciarProduccion::class)->execute([['producto_id' => $producto->id, 'cantidad' => 100]], $admin->id);
    $this->actingAs($admin)->get(route('admin.productos.index'))->assertSee('0 unidades')->assertSee('Sin stock');

    app(FinalizarProduccion::class)->execute($lote->id(), [ProduccionDetalleModel::first()->id => 98], $admin->id);

    $html = $this->actingAs($admin)->get(route('admin.productos.index'))->assertOk();
    $html->assertSee('98 unidades');
    $html->assertSee('Disponible');
});

it('se puede filtrar por estado y buscar por nombre o tipo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_stock_leche(500.0, $admin->id);
    $queso = crearRecetaPorHttp($this, $admin);
    crearRecetaPorHttp($this, $admin, ['tipo_producto' => 'Yogurt', 'nombre_producto' => 'Yogurt Fresa', 'presentacion_unidad' => 'L']);
    $lote = app(IniciarProduccion::class)->execute([['producto_id' => $queso->id, 'cantidad' => 5]], $admin->id);
    app(FinalizarProduccion::class)->execute($lote->id(), [ProduccionDetalleModel::first()->id => 5], $admin->id);

    $disponibles = $this->actingAs($admin)->get(route('admin.productos.index', ['estado' => 'DISPONIBLE']));
    $disponibles->assertSee('Queso Paria')->assertDontSee('Yogurt Fresa');

    $sinStock = $this->actingAs($admin)->get(route('admin.productos.index', ['estado' => 'SIN_STOCK']));
    $sinStock->assertSee('Yogurt Fresa')->assertDontSee('Queso Paria');

    $this->actingAs($admin)->get(route('admin.productos.index', ['q' => 'Yogurt']))->assertSee('Yogurt Fresa')->assertDontSee('Queso Paria');
});

it('los productos históricos sin receta aparecen como SIN RECETA con enlace para configurarlos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $historico = fixture_producto('queso quesoso', 'Kl');

    $html = $this->actingAs($admin)->get(route('admin.productos.index'))->assertOk();

    $html->assertSee('queso quesoso');
    $html->assertSee('Sin receta');
    $html->assertSee(route('admin.recetas.index', ['configurar' => $historico->id]), false);
});

it('solo el administrador ve el inventario de productos del panel de administración', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $producto = fixture_producto('Queso');

    $this->actingAs($encargado)->get(route('admin.productos.index'))->assertForbidden();
    $this->actingAs($encargado)->get(route('admin.productos.show', $producto->id))->assertForbidden();
});

it('el menú lateral del administrador sigue la estructura pedida', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertSeeInOrder([
        'Inicio', 'Operación', 'Acopio', 'Proveedores', 'Acopiadores', 'Zonas', 'Calidad', 'Configuración',
        'Parámetros de calidad', 'Reglas de calidad', 'Configuración de pagos', 'Finanzas', 'Pagos',
        'Producción', 'Recetas', 'Producción', 'Inventario', 'Insumos', 'Productos', 'Ventas', 'Ventas', 'Compradores',
        'Comunicación', 'Comunicados', 'Administración', 'Encargados de planta', 'Personal de ventas', 'Analistas de calidad', 'Auditoría',
    ]);
});

it('los breadcrumbs reflejan la ubicación conceptual de cada módulo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->get(route('admin.recetas.index'))->assertSeeInOrder(['Producción', '›', 'Recetas']);
    $this->actingAs($admin)->get(route('admin.productos.index'))->assertSeeInOrder(['Inventario', '›', 'Productos']);
    $this->actingAs($admin)->get(route('admin.insumos.index'))->assertSeeInOrder(['Inventario', '›', 'Insumos']);
});
