<?php

use App\Application\Produccion\ActualizarReceta;
use App\Application\Produccion\GuardarPresentacionInsumo;
use App\Application\Ventas\ConfigurarPreciosProducto;
use App\Domain\Produccion\Insumo;
use App\Domain\Produccion\Receta;
use App\Infrastructure\Persistence\Models\CompraInsumoModel;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\ProductoPrecioModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

// Estas pruebas no tratan de asistencia: el personal ya tiene su jornada PRESENTE de hoy.
beforeEach(fn () => fixture_personal_con_asistencia_hoy());

/**
 * Queso Fresco: 10 L leche + 15 ml cuajo + 30 g sal por unidad.
 *
 * @return array{0: ProductoModel, 1: Insumo, 2: Insumo, 3: Receta}
 */
function escenarioPlantaMovil(int $adminId, float $sal = 5000.0): array
{
    fixture_stock_leche(245.0, $adminId);
    $cuajo = fixture_insumo('Cuajo', 'ml', 500.0, 200.0, $adminId);
    $salInsumo = fixture_insumo('Sal', 'g', $sal, 0.0, $adminId);
    $queso = fixture_producto('Queso Fresco 1 kg');

    $receta = fixture_receta($queso->id, [
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 10.0, 'unidad' => 'L'],
        ['insumo_id' => $cuajo->id(), 'cantidad' => 15.0, 'unidad' => 'ml'],
        ['insumo_id' => $salInsumo->id(), 'cantidad' => 30.0, 'unidad' => 'g'],
    ], $adminId);

    return [$queso, $cuajo, $salInsumo, $receta];
}

it('el encargado consulta productos y recetas, pero no puede tocar precios ni recetas', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso, , , $receta] = escenarioPlantaMovil($admin->id);
    $planta = fixture_usuario('ENCARGADO_PLANTA');

    $this->actingAs($planta, 'sanctum')->getJson(route('api.planta.productos.index'))->assertOk();
    $this->actingAs($planta, 'sanctum')->getJson(route('api.planta.recetas.index'))->assertOk();

    $this->actingAs($planta)->put(route('admin.producto-precios.update', $queso->id), ['precio_publico' => 1])->assertForbidden();
    $this->actingAs($planta)->delete(route('admin.producto-precios.destroy', $queso->id))->assertForbidden();
    $this->actingAs($planta)->post(route('admin.recetas.store'), [])->assertForbidden();
    $this->actingAs($planta)->patch(route('admin.recetas.update', $receta->id()), [])->assertForbidden();
    $this->actingAs($planta)->patch(route('admin.recetas.desactivar', $receta->id()))->assertForbidden();

    expect(ProductoPrecioModel::count())->toBe(0);
});

it('muestra los precios vigentes que configura el administrador, solo como lectura', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso] = escenarioPlantaMovil($admin->id);
    $planta = fixture_usuario('ENCARGADO_PLANTA');

    $this->actingAs($planta, 'sanctum')->getJson(route('api.planta.productos.show', $queso->id))
        ->assertOk()
        ->assertJsonPath('data.precios.publico', null)
        ->assertJsonPath('data.receta.estado', 'ACTIVA');

    app(ConfigurarPreciosProducto::class)->execute($queso->id, 4.0, 5.0, 4.5, 12.0, $admin->id);

    $this->actingAs($planta, 'sanctum')->getJson(route('api.planta.productos.show', $queso->id))
        ->assertOk()
        ->assertJsonPath('data.precios.publico.precio', 5)
        ->assertJsonPath('data.precios.mayorista.precio', 4.5)
        ->assertJsonPath('data.precios.mayorista.cantidad_minima', 12)
        ->assertJsonPath('data.precios.proveedor.precio', 4);
});

it('la receta activa del administrador define lo que necesita una nueva producción', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso, $cuajo, , $receta] = escenarioPlantaMovil($admin->id);
    $planta = fixture_usuario('ENCARGADO_PLANTA');

    $this->actingAs($planta, 'sanctum')->getJson(route('api.planta.recetas.index'))
        ->assertOk()
        ->assertJsonPath('data.filas.0.por_unidad.0.cantidad', 10)
        ->assertJsonPath('data.filas.0.por_unidad.1.cantidad', 15);

    // El administrador cambia la receta (aún no usada): el cálculo de planta la sigue.
    app(ActualizarReceta::class)->execute($receta->id(), null, [
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 10.0, 'unidad' => 'L'],
        ['insumo_id' => $cuajo->id(), 'cantidad' => 20.0, 'unidad' => 'ml'],
    ]);

    $this->actingAs($planta, 'sanctum')->postJson(route('api.planta.produccion.requerimientos'), [
        'productos' => [['producto_id' => $queso->id, 'cantidad' => 10]],
    ])->assertOk()
        ->assertJsonPath('data.insumos.0.necesario', 100)
        ->assertJsonPath('data.insumos.1.necesario', 200)
        ->assertJsonCount(2, 'data.insumos')
        ->assertJsonPath('data.puede_iniciar', true);
});

it('inicia la producción con stock suficiente y no la duplica al reintentar con la misma clave', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso] = escenarioPlantaMovil($admin->id);
    $planta = fixture_usuario('ENCARGADO_PLANTA');
    $cuerpo = ['productos' => [['producto_id' => $queso->id, 'cantidad' => 12]]];

    $primera = $this->actingAs($planta, 'sanctum')
        ->postJson(route('api.planta.produccion.store'), $cuerpo, ['Idempotency-Key' => 'prod-local-1'])
        ->assertCreated();

    $this->actingAs($planta, 'sanctum')
        ->postJson(route('api.planta.produccion.store'), $cuerpo, ['Idempotency-Key' => 'prod-local-1'])
        ->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertJsonPath('data.id', $primera->json('data.id'));

    expect(LoteProduccionModel::count())->toBe(1);
    // 12 x 10 L = 120 L descontados una sola vez.
    expect((float) fixture_leche()->fresh()->stock_actual)->toBe(125.0);
});

it('bloquea la producción si falta un insumo y no deja stock negativo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso, , $sal] = escenarioPlantaMovil($admin->id, sal: 100.0);
    $planta = fixture_usuario('ENCARGADO_PLANTA');

    // 10 unidades x 30 g = 300 g de sal; solo hay 100 g.
    $this->actingAs($planta, 'sanctum')
        ->postJson(route('api.planta.produccion.store'), ['productos' => [['producto_id' => $queso->id, 'cantidad' => 10]]], ['Idempotency-Key' => 'prod-sin-sal'])
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'motivos']);

    expect(LoteProduccionModel::count())->toBe(0);
    expect((float) InsumoModel::find($sal->id())->stock_actual)->toBe(100.0);
});

it('registra la compra de insumos con la presentación configurada, una sola vez', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [, , $sal] = escenarioPlantaMovil($admin->id);
    $saco = app(GuardarPresentacionInsumo::class)->execute((int) $sal->id(), 'Saco', 25.0, 'kg');
    $planta = fixture_usuario('ENCARGADO_PLANTA');

    $this->actingAs($planta, 'sanctum')->getJson(route('api.planta.insumos.show', $sal->id()))
        ->assertOk()
        ->assertJsonPath('data.presentaciones.0.cantidad_base', 25000)
        ->assertJsonPath('data.insumo.es_leche', false);

    $cuerpo = ['lineas' => [['insumo_id' => $sal->id(), 'presentacion_id' => $saco->id(), 'cantidad' => 2]], 'total' => 200];

    $this->actingAs($planta, 'sanctum')->postJson(route('api.planta.compras-insumo.store'), $cuerpo, ['Idempotency-Key' => 'compra-1'])->assertCreated();
    $this->actingAs($planta, 'sanctum')->postJson(route('api.planta.compras-insumo.store'), $cuerpo, ['Idempotency-Key' => 'compra-1'])->assertCreated();

    expect(CompraInsumoModel::count())->toBe(1);
    // 5000 g + 2 x 25 kg = 55 000 g.
    expect((float) InsumoModel::find($sal->id())->stock_actual)->toBe(55000.0);
});

it('no permite comprar leche: su stock sube solo por acopio', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    escenarioPlantaMovil($admin->id);
    $planta = fixture_usuario('ENCARGADO_PLANTA');

    $this->actingAs($planta, 'sanctum')->getJson(route('api.planta.compras-insumo.catalogo'))
        ->assertOk()
        ->assertJsonMissing(['nombre' => fixture_leche()->nombre]);
});

it('entrega el QR del encargado para mostrarlo sin conexión', function () {
    $planta = fixture_usuario('ENCARGADO_PLANTA');

    $this->actingAs($planta, 'sanctum')->getJson(route('api.planta.perfil'))
        ->assertOk()
        ->assertJsonPath('data.rol', 'Encargado de planta')
        ->assertJsonStructure(['data' => ['qr_token', 'dni', 'nombres', 'apellidos']]);
});
