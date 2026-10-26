<?php

use App\Application\Produccion\BoletaIlegibleException;
use App\Application\Produccion\Contracts\BoletaCompraOcrService;
use App\Application\Produccion\GuardarPresentacionInsumo;
use App\Infrastructure\Persistence\Models\CompraInsumoModel;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\PresentacionInsumoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

use function Pest\Laravel\mock;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/**
 * @return array{sal: int, saco: int}
 */
function compra_web_sal(int $userId): array
{
    $sal = fixture_insumo('Sal', 'g', 1430.0, 0.0, $userId);

    return ['sal' => $sal->id(), 'saco' => app(GuardarPresentacionInsumo::class)->execute($sal->id(), 'Saco', 25, 'kg')->id()];
}

it('procesar boleta guarda la foto original y propone las líneas sin registrar la compra', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = compra_web_sal($admin->id);
    mock(BoletaCompraOcrService::class)->shouldReceive('leerLecturas')->once()->andReturn(["RUC 20601234567\n2 SACOS SAL"]);

    $respuesta = $this->actingAs($admin)->postJson(route('admin.compras-insumo.boleta'), ['imagen' => UploadedFile::fake()->image('boleta.jpg')]);

    $respuesta->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('lineas.0.insumo_id', $e['sal'])
        ->assertJsonPath('lineas.0.presentacion_id', $e['saco'])
        ->assertJsonPath('cabecera.ruc', '20601234567');
    expect($respuesta->json('imagen_boleta'))->toStartWith('compras-insumo/boletas/boleta-');
    Storage::disk('public')->assertExists($respuesta->json('imagen_boleta'));
    $this->assertDatabaseCount('compras_insumo', 0);
    expect((float) InsumoModel::find($e['sal'])->stock_actual)->toBe(1430.0);
});

it('si el OCR no puede leer la boleta responde el mensaje para reintentar y conserva la foto', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');
    mock(BoletaCompraOcrService::class)->shouldReceive('leerLecturas')->andThrow(new BoletaIlegibleException('tesseract no encontrado'));

    $respuesta = $this->actingAs($admin)->postJson(route('admin.compras-insumo.boleta'), ['imagen' => UploadedFile::fake()->image('boleta.jpg')]);

    $respuesta->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('mensaje', 'No se pudo leer la boleta automáticamente.');
    Storage::disk('public')->assertExists($respuesta->json('imagen_boleta'));
});

it('procesar boleta exige una imagen', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->postJson(route('admin.compras-insumo.boleta'), ['imagen' => UploadedFile::fake()->create('boleta.pdf', 10, 'application/pdf')])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('imagen');
});

it('previsualizar devuelve el total en unidad base y el stock resultante', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = compra_web_sal($admin->id);

    $this->actingAs($admin)->postJson(route('admin.compras-insumo.previsualizar'), ['lineas' => [['insumo_id' => $e['sal'], 'presentacion_id' => $e['saco'], 'cantidad' => 2]]])
        ->assertOk()
        ->assertJsonPath('data.lineas.0.total', 50000)
        ->assertJsonPath('data.impacto.0.stock_resultante', 51430);
});

it('confirmar la compra desde la boleta registra quién la confirmó, la foto y aumenta el stock', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = compra_web_sal($admin->id);
    $foto = UploadedFile::fake()->image('boleta.jpg')->storeAs('compras-insumo/boletas', 'boleta-'.str_repeat('a', 24).'.jpg', 'public');

    $respuesta = $this->actingAs($admin)->post(route('admin.compras-insumo.store'), [
        'lineas' => [['insumo_id' => $e['sal'], 'presentacion_id' => $e['saco'], 'cantidad' => 2]],
        'proveedor_comercial' => 'Distribuidora X',
        'numero_comprobante' => 'B001-203',
        'imagen_boleta' => $foto,
        // Un usuario distinto en el request se ignora: el actor es siempre el autenticado.
        'registrado_por_user_id' => 999,
    ]);

    $compra = CompraInsumoModel::sole();
    $respuesta->assertRedirect(route('admin.compras-insumo.show', $compra->id))
        ->assertSessionHas('success', "Compra #{$compra->id} registrada. El stock de los insumos ya fue actualizado.");
    expect($compra->origen)->toBe('BOLETA');
    expect($compra->imagen_boleta)->toBe($foto);
    expect($compra->registrado_por_user_id)->toBe($admin->id);
    expect((float) InsumoModel::find($e['sal'])->stock_actual)->toBe(51430.0);

    $this->actingAs($admin)->get(route('admin.compras-insumo.show', $compra->id))
        ->assertOk()
        ->assertSeeInOrder(['Compra #'.$compra->id, 'Distribuidora X', 'B001-203', $admin->username, 'Sal', '2 × Saco 25 kg', '+50,000 g']);
});

it('rechaza una foto de boleta que no salió de Procesar boleta', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = compra_web_sal($admin->id);

    $this->actingAs($admin)->post(route('admin.compras-insumo.store'), [
        'lineas' => [['insumo_id' => $e['sal'], 'presentacion_id' => $e['saco'], 'cantidad' => 2]],
        'imagen_boleta' => '../../.env',
    ])->assertSessionHasErrors('imagen_boleta');

    $this->assertDatabaseCount('compras_insumo', 0);
});

it('comprar leche por HTTP se rechaza sin tocar su stock', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = compra_web_sal($admin->id);
    fixture_stock_leche(100.0, $admin->id);

    $this->actingAs($admin)->post(route('admin.compras-insumo.store'), [
        'lineas' => [['insumo_id' => fixture_leche()->id, 'presentacion_id' => $e['saco'], 'cantidad' => 2]],
    ])->assertSessionHas('error', 'La leche no se compra: su stock aumenta solo desde Acopio.');

    expect((float) fixture_leche()->fresh()->stock_actual)->toBe(100.0);
});

it('el panel permite agregar y editar presentaciones con su equivalencia calculada', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $cuajo = fixture_insumo('Cuajo', 'ml', 0.0, 0.0, $admin->id);

    $this->actingAs($admin)->post(route('admin.insumos.presentaciones.store', $cuajo->id()), ['nombre' => 'Balde', 'cantidad' => 20, 'unidad' => 'L'])
        ->assertRedirect(route('admin.insumos.index', ['insumo' => $cuajo->id()]))
        ->assertSessionHas('success', 'Presentación agregada. Balde 20 L = 20,000 ml.');

    $presentacion = PresentacionInsumoModel::sole();
    $this->actingAs($admin)->patch(route('admin.insumos.presentaciones.update', [$cuajo->id(), $presentacion->id]), ['nombre' => 'Bidón', 'cantidad' => 5, 'unidad' => 'L', 'estado' => 'ACTIVA'])
        ->assertSessionHas('success');
    expect((float) $presentacion->fresh()->cantidad_base)->toBe(5000.0);

    $this->actingAs($admin)->get(route('admin.insumos.panel', $cuajo->id()))
        ->assertOk()
        ->assertSee('Presentaciones de compra')
        ->assertSee('Bidón 5 L')
        ->assertSee('= 5,000 ml')
        ->assertSee('Registrar compra');
});

it('una presentación con unidad incompatible vuelve con el error explicado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $sal = fixture_insumo('Sal', 'g', 0.0, 0.0, $admin->id);

    $this->actingAs($admin)->post(route('admin.insumos.presentaciones.store', $sal->id()), ['nombre' => 'Balde', 'cantidad' => 20, 'unidad' => 'L'])
        ->assertSessionHas('error', 'La unidad L no es compatible con Sal, que se controla en g.');
    $this->assertDatabaseCount('presentaciones_insumo', 0);
});

it('el encargado de planta no accede a las compras ni a las presentaciones del portal administrador', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $sal = fixture_insumo('Sal', 'g', 0.0, 0.0, $encargado->id);

    $this->actingAs($encargado)->postJson(route('admin.compras-insumo.previsualizar'), ['lineas' => []])->assertForbidden();
    $this->actingAs($encargado)->post(route('admin.insumos.presentaciones.store', $sal->id()), ['nombre' => 'Saco', 'cantidad' => 25, 'unidad' => 'kg'])->assertForbidden();
    $this->assertDatabaseCount('presentaciones_insumo', 0);
});

it('sin sesión las rutas de compra redirigen al login', function () {
    $this->post(route('admin.compras-insumo.store'), [])->assertRedirect(route('login'));
});

it('una compra manual con precio unitario por línea calcula el total, guarda la foto adjunta y la fecha de última compra', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = compra_web_sal($admin->id);

    $this->actingAs($admin)->postJson(route('admin.compras-insumo.previsualizar'), ['lineas' => [['insumo_id' => $e['sal'], 'presentacion_id' => $e['saco'], 'cantidad' => 2, 'precio_unitario' => 45.5]]])
        ->assertOk()
        ->assertJsonPath('data.lineas.0.subtotal', 91)
        ->assertJsonPath('data.total_precios', 91);

    $this->actingAs($admin)->post(route('admin.compras-insumo.store'), [
        'lineas' => [['insumo_id' => $e['sal'], 'presentacion_id' => $e['saco'], 'cantidad' => 2, 'precio_unitario' => 45.5]],
        'proveedor_comercial' => 'Bodega Central',
        'fecha' => '2026-10-20',
        'imagen' => UploadedFile::fake()->image('boleta.jpg'),
    ])->assertRedirect();

    $compra = CompraInsumoModel::firstOrFail();
    expect((float) $compra->total)->toBe(91.0);
    expect((float) InsumoModel::find($e['sal'])->stock_actual)->toBe(51430.0);
    expect((float) $compra->detalles()->value('precio_unitario'))->toBe(45.5);
    Storage::disk('public')->assertExists($compra->imagen_boleta);

    $this->actingAs($admin)->get(route('admin.insumos.index'))->assertOk()->assertSee('20/10/2026');
    $this->actingAs($admin)->get(route('admin.compras-insumo.show', $compra->id))->assertOk()->assertSee('S/ 45.50 c/u = S/ 91.00');
});

it('sin precio en todas las líneas la compra no inventa un total', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = compra_web_sal($admin->id);

    $this->actingAs($admin)->post(route('admin.compras-insumo.store'), [
        'lineas' => [['insumo_id' => $e['sal'], 'presentacion_id' => $e['saco'], 'cantidad' => 1]],
    ])->assertRedirect();

    expect(CompraInsumoModel::firstOrFail()->total)->toBeNull();
});
