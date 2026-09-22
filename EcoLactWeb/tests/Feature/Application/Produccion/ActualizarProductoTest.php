<?php

use App\Application\Produccion\CambiarImagenProducto;
use App\Application\Produccion\CrearNuevaVersionReceta;
use App\Application\Produccion\CrearRecetaConProducto;
use App\Application\Produccion\GuardarCambiosReceta;
use App\Application\Produccion\ImagenSubida;
use App\Application\Produccion\IniciarProduccion;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/**
 * El producto ya no se edita desde Productos (antes ActualizarProducto): su fórmula
 * se cambia desde la receta y su imagen solo con la acción explícita "Cambiar imagen".
 */
/**
 * Un UploadedFile falso borra su archivo temporal al destruirse: se conserva viva la referencia.
 */
function archivoFalso(string $nombre, int $ancho = 120, int $alto = 120): UploadedFile
{
    static $conservados = [];

    return $conservados[] = UploadedFile::fake()->image($nombre, $ancho, $alto);
}

function recetaConImagen(int $userId): array
{
    $imagen = archivoFalso('original.png', 200, 200);

    $receta = app(CrearRecetaConProducto::class)->execute(
        tipoProducto: 'Queso',
        nombreBase: 'Queso Paria',
        presentacionCantidad: 1.0,
        presentacionUnidad: 'kg',
        descripcion: null,
        ingredientes: [['insumo_id' => fixture_leche()->id, 'cantidad' => 10.0, 'unidad' => 'L']],
        creadaPorUserId: $userId,
        imagen: new ImagenSubida($imagen->getRealPath(), 'png'),
    );

    return [ProductoModel::findOrFail($receta->productoId()), $receta];
}

function nuevaImagenSubida(string $nombre = 'nueva.png'): ImagenSubida
{
    return new ImagenSubida(archivoFalso($nombre)->getRealPath(), 'png');
}

it('la imagen se guarda UNA sola vez, en products/, y la base solo guarda su ruta', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');

    [$producto] = recetaConImagen($admin->id);

    expect($producto->imagen)->toStartWith('products/queso-paria-1-kg-')->toEndWith('.png');
    expect(Storage::disk('public')->allFiles())->toBe([$producto->imagen]);
    expect(Storage::disk('public')->allFiles('recetas'))->toBe([]);
    expect(Schema::hasColumn('recetas', 'imagen'))->toBeFalse();
});

it('cambiar la imagen es una acción explícita: guarda la nueva y elimina la anterior', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');
    [$producto] = recetaConImagen($admin->id);
    $anterior = $producto->imagen;

    $actualizado = app(CambiarImagenProducto::class)->execute($producto->id, nuevaImagenSubida());

    expect($actualizado->imagen())->not->toBe($anterior);
    expect(Storage::disk('public')->exists($anterior))->toBeFalse();
    expect(Storage::disk('public')->allFiles())->toBe([$actualizado->imagen()]);
    expect($producto->fresh()->imagen)->toBe($actualizado->imagen());
});

it('no borra la imagen anterior si otro producto todavía la usa', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');
    [$producto] = recetaConImagen($admin->id);
    $compartida = $producto->imagen;
    $otro = fixture_producto('Otro producto');
    $otro->update(['imagen' => $compartida]);

    app(CambiarImagenProducto::class)->execute($producto->id, nuevaImagenSubida());

    expect(Storage::disk('public')->exists($compartida))->toBeTrue();
});

it('cambiar el contenido de la receta o crear una versión no toca ni duplica la imagen', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_stock_leche(200.0, $admin->id);
    [$producto, $v1] = recetaConImagen($admin->id);
    $imagen = $producto->imagen;
    app(IniciarProduccion::class)->execute([['producto_id' => $producto->id, 'cantidad' => 1]], $admin->id);

    $v2 = app(GuardarCambiosReceta::class)->execute($v1->id(), null, [
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 12.0, 'unidad' => 'L'],
    ], $admin->id);
    $v3 = app(CrearNuevaVersionReceta::class)->execute($v2->id(), null, [
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 13.0, 'unidad' => 'L'],
    ], $admin->id);

    expect($v2->version())->toBe(2);
    expect($v3->version())->toBe(3);
    expect($producto->fresh()->imagen)->toBe($imagen);
    expect(Storage::disk('public')->allFiles())->toBe([$imagen]);
    expect(ProductoModel::count())->toBe(1);
});

it('editar una receta con una imagen nueva reemplaza la del producto', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');
    [$producto, $receta] = recetaConImagen($admin->id);
    $anterior = $producto->imagen;

    app(GuardarCambiosReceta::class)->execute($receta->id(), 'Nueva', [
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 11.0, 'unidad' => 'L'],
    ], $admin->id, nuevaImagenSubida());

    expect($producto->fresh()->imagen)->not->toBe($anterior);
    expect(Storage::disk('public')->allFiles())->toHaveCount(1);
});

it('un producto sin imagen usa el placeholder (sin URL)', function () {
    $producto = fixture_producto('Sin foto');

    expect($producto->imagenUrl())->toBeNull();
});
