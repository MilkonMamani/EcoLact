<?php

use App\Application\Produccion\FinalizarProduccion;
use App\Application\Produccion\IniciarProduccion;
use App\Infrastructure\Persistence\Models\ProduccionDetalleModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\ProductoPrecioModel;
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
        'categoria_id' => fixture_categoria_id('Quesos'),
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

    $html->assertSee('Consulta tus productos y administra sus precios de venta.');
    $html->assertSee('Queso Paria');
    $html->assertSee('Quesos');
    $html->assertSee('1 kg');
    $html->assertSee('0 unidades');
    $html->assertSee('Receta activa');
    $html->assertSee('Sin stock');
});

it('Productos NO es un CRUD: no hay "+ Nuevo producto", ni rutas de crear, editar o ver detalle', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = crearRecetaPorHttp($this, $admin);

    $html = $this->actingAs($admin)->get(route('admin.productos.index'))->assertOk();

    $html->assertDontSee('Nuevo producto');
    $html->assertDontSee('Desactivar');
    expect(Route::has('admin.productos.show'))->toBeFalse();
    expect(Route::has('admin.productos.store'))->toBeFalse();
    expect(Route::has('admin.productos.update'))->toBeFalse();
    $this->actingAs($admin)->post('/admin/productos', ['nombre' => 'Manual'])->assertStatus(405);
    $this->actingAs($admin)->patch('/admin/productos/'.$producto->id, ['estado' => 'INACTIVO'])->assertNotFound();
    expect(ProductoModel::where('nombre', 'Manual')->exists())->toBeFalse();
});

it('la tabla muestra solo Producto, Categoría, Presentación, Stock actual, Estado y Acción', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');
    crearRecetaPorHttp($this, $admin, ['imagen' => UploadedFile::fake()->image('queso.png')]);

    $this->actingAs($admin)->get(route('admin.productos.index'))
        ->assertSeeInOrder(['Producto', 'Categoría', 'Presentación', 'Stock actual', 'Estado', 'Acción'])
        ->assertDontSee('Última producción')
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
    expect(Storage::disk('public')->allFiles())->toBe([$ruta]);
});

it('valida la imagen: tipo MIME y tamaño máximo de 2 MB', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');
    $base = [
        'categoria_id' => fixture_categoria_id('Quesos'), 'nombre_producto' => 'Queso Paria', 'presentacion_cantidad' => 1, 'presentacion_unidad' => 'kg',
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

it('sin precios, la fila desplegada muestra "Sin precios configurados" y "Configurar precios"', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    crearRecetaPorHttp($this, $admin);

    $this->actingAs($admin)->get(route('admin.productos.index'))->assertOk()
        ->assertSeeInOrder(['Gestión de precios', 'Sin precios configurados', 'Configurar precios'])
        ->assertDontSee('Eliminar precio');
});

it('guarda los tres precios y el umbral mayorista; solo se ven en la fila desplegada', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = crearRecetaPorHttp($this, $admin);

    $this->actingAs($admin)->put(route('admin.producto-precios.update', $producto->id), [
        'producto_id' => $producto->id,
        'precio_proveedor' => 18,
        'precio_publico' => 20,
        'precio_mayorista' => 19,
        'cantidad_minima_mayorista' => 10,
    ])->assertRedirect()->assertSessionHas('productoAbierto', $producto->id);

    expect(ProductoPrecioModel::where('producto_id', $producto->id)->where('estado', 'ACTIVO')->pluck('precio', 'tipo_cliente')->map(fn ($p) => (float) $p)->all())
        ->toEqualCanonicalizing(['PROVEEDOR' => 18.0, 'PUBLICO' => 20.0, 'MAYORISTA' => 19.0]);

    $this->actingAs($admin)->get(route('admin.productos.index'))
        ->assertSeeInOrder(['Gestión de precios', 'S/ 18.00', 'S/ 20.00', 'S/ 19.00', 'desde 10 unidades', 'Editar precios', 'Eliminar precio'])
        ->assertDontSee('Sin precios configurados');
});

it('editar un precio cierra el anterior, no toca los que no cambian y un campo vacío lo retira', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = crearRecetaPorHttp($this, $admin);
    $url = route('admin.producto-precios.update', $producto->id);

    $this->actingAs($admin)->put($url, ['precio_proveedor' => 18, 'precio_publico' => 20, 'precio_mayorista' => 19]);
    $this->actingAs($admin)->put($url, ['precio_proveedor' => 18, 'precio_publico' => 21]);

    $activos = ProductoPrecioModel::where('producto_id', $producto->id)->where('estado', 'ACTIVO')->get();
    expect($activos->pluck('precio', 'tipo_cliente')->map(fn ($p) => (float) $p)->all())->toEqualCanonicalizing(['PROVEEDOR' => 18.0, 'PUBLICO' => 21.0]);
    expect(ProductoPrecioModel::where('producto_id', $producto->id)->where('tipo_cliente', 'PROVEEDOR')->count())->toBe(1);
    expect(ProductoPrecioModel::where('producto_id', $producto->id)->where('estado', 'INACTIVO')->pluck('tipo_cliente')->all())->toEqualCanonicalizing(['PUBLICO', 'MAYORISTA']);
});

it('"Eliminar precio" deja el producto sin precios vigentes sin borrar el historial', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = crearRecetaPorHttp($this, $admin);
    $this->actingAs($admin)->put(route('admin.producto-precios.update', $producto->id), ['precio_publico' => 20]);

    $this->actingAs($admin)->delete(route('admin.producto-precios.destroy', $producto->id))->assertRedirect();

    expect(ProductoPrecioModel::where('producto_id', $producto->id)->count())->toBe(1);
    expect(ProductoPrecioModel::where('producto_id', $producto->id)->where('estado', 'ACTIVO')->exists())->toBeFalse();
    $this->actingAs($admin)->get(route('admin.productos.index'))->assertSee('Sin precios configurados');
});

it('valida los precios: al menos uno, positivos, y el umbral exige precio mayorista', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = crearRecetaPorHttp($this, $admin);
    $url = route('admin.producto-precios.update', $producto->id);

    $this->actingAs($admin)->put($url, [])->assertSessionHasErrors('precio_proveedor');
    $this->actingAs($admin)->put($url, ['precio_publico' => -5])->assertSessionHasErrors('precio_publico');
    $this->actingAs($admin)->put($url, ['precio_publico' => 20, 'cantidad_minima_mayorista' => 10])->assertSessionHasErrors('precio_mayorista');

    expect(ProductoPrecioModel::count())->toBe(0);
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
    crearRecetaPorHttp($this, $admin, ['categoria_id' => fixture_categoria_id('Yogures'), 'nombre_producto' => 'Yogurt Fresa', 'presentacion_unidad' => 'L']);
    $lote = app(IniciarProduccion::class)->execute([['producto_id' => $queso->id, 'cantidad' => 5]], $admin->id);
    app(FinalizarProduccion::class)->execute($lote->id(), [ProduccionDetalleModel::first()->id => 5], $admin->id);

    $disponibles = $this->actingAs($admin)->get(route('admin.productos.index', ['estado' => 'DISPONIBLE']));
    $disponibles->assertSee('Queso Paria')->assertDontSee('Yogurt Fresa');

    $sinStock = $this->actingAs($admin)->get(route('admin.productos.index', ['estado' => 'SIN_STOCK']));
    $sinStock->assertSee('Yogurt Fresa')->assertDontSee('Queso Paria');

    $this->actingAs($admin)->get(route('admin.productos.index', ['q' => 'Yogurt']))->assertSee('Yogurt Fresa')->assertDontSee('Queso Paria');
});

it('los productos históricos sin receta aparecen sin receta activa con enlace para configurarlos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $historico = fixture_producto('queso quesoso', 'Kl');

    $html = $this->actingAs($admin)->get(route('admin.productos.index'))->assertOk();

    $html->assertSee('queso quesoso');
    $html->assertSee('Sin receta activa');
    $html->assertSee(route('admin.recetas.index', ['configurar' => $historico->id]), false);
});

it('solo el administrador ve el inventario de productos del panel de administración', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $producto = fixture_producto('Queso');

    $this->actingAs($encargado)->get(route('admin.productos.index'))->assertForbidden();
    $this->actingAs($encargado)->put(route('admin.producto-precios.update', $producto->id), ['precio_publico' => 20])->assertForbidden();
    expect(ProductoPrecioModel::count())->toBe(0);
});

it('el menú lateral del administrador sigue la estructura pedida', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertSeeInOrder([
        'Dashboard', 'Inicio', 'Asistencia', 'Operación', 'Acopio', 'Proveedores', 'Zonas',
        'Personal', 'Trabajadores', 'Nuevo trabajador', 'Acopiadores', 'Analistas de calidad', 'Encargados de planta', 'Personal de ventas',
        'Calidad', 'Configuración',
        'Parámetros de calidad', 'Configuración de pagos', 'Finanzas', 'Pagos',
        'Producción', 'Recetas', 'Producción', 'Inventario', 'Insumos', 'Productos', 'Ventas', 'Ventas', 'Compradores',
        'Comunicación', 'Comunicados', 'Administración', 'Auditoría',
    ]);
});

it('los breadcrumbs reflejan la ubicación conceptual de cada módulo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->get(route('admin.recetas.index'))->assertSeeInOrder(['Producción', '›', 'Recetas']);
    $this->actingAs($admin)->get(route('admin.productos.index'))->assertSeeInOrder(['Inventario', '›', 'Productos']);
    $this->actingAs($admin)->get(route('admin.insumos.index'))->assertSeeInOrder(['Inventario', '›', 'Insumos']);
});
