<?php

use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use App\Infrastructure\Persistence\Models\ProduccionDetalleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

// Estas pruebas no tratan de asistencia: el personal ya tiene su jornada PRESENTE de hoy.
beforeEach(fn () => fixture_personal_con_asistencia_hoy());

it('la pantalla de producción muestra los KPIs y el historial', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $lote = fixture_lote(40.0, $admin->id);

    $response = $this->actingAs($encargado)->get(route('planta.produccion.index'));

    $response->assertOk();
    $response->assertSee('Lotes en proceso');
    $response->assertSee($lote->codigo());
    $response->assertSee('40 L');
    $response->assertSee('Nueva producción');
});

it('Nueva producción es un modal del historial, con la leche disponible en una línea simple', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    fixture_stock_leche(40.0, $admin->id);
    $queso = fixture_producto('Queso Paria 1 kg');
    fixture_receta($queso->id, [['insumo_id' => fixture_leche()->id, 'cantidad' => 10.0, 'unidad' => 'L']], $admin->id);

    $response = $this->actingAs($encargado)->get(route('planta.produccion.index'));

    $response->assertOk();
    $response->assertSee('Gestiona los lotes de producción y registra los productos obtenidos.');
    $response->assertSee('Leche disponible');
    $response->assertSee('40 L');
    $response->assertDontSee('Stock de leche');
    $response->assertDontSee('La leche sube sola');
    $response->assertSee('data-modal-open="modal-nueva-produccion"', false);
    $response->assertSee('id="modal-nueva-produccion"', false);
    $response->assertSee('Selecciona qué fabricar. EcoLact calculará automáticamente los insumos necesarios.');
    $response->assertSee('Iniciar producción');
    // No hay una página visible de "crear": la ruta antigua redirige al historial abriendo el modal.
    $this->actingAs($encargado)->get(route('planta.produccion.create'))->assertRedirect(route('planta.produccion.index', ['nueva' => 1]));
});

it('un encargado de planta elige producto y cantidad, inicia la producción y luego la finaliza con lo realmente obtenido', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_stock_leche(1000.0, $admin->id);
    $cuajo = fixture_insumo('Cuajo', 'ml', 800.0, 0.0, $admin->id);
    $producto = fixture_producto('Queso Paria', 'unidad');
    fixture_receta($producto->id, [
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 10.0, 'unidad' => 'L'],
        ['insumo_id' => $cuajo->id(), 'cantidad' => 5.0, 'unidad' => 'ml'],
    ], $admin->id);

    $previsualizar = $this->actingAs($encargado)->postJson(route('planta.produccion.requerimientos'), [
        'productos' => [['producto_id' => $producto->id, 'cantidad' => 20]],
    ]);
    $previsualizar->assertOk()->assertJsonPath('data.puede_iniciar', true);
    expect((float) fixture_leche()->fresh()->stock_actual)->toBe(1000.0);

    $response = $this->actingAs($encargado)->post(route('planta.produccion.store'), [
        'productos' => [['producto_id' => $producto->id, 'cantidad' => 20]],
    ]);

    $lote = LoteProduccionModel::firstOrFail();
    $response->assertRedirect(route('planta.produccion.show', $lote->id));
    expect($lote->estado)->toBe('EN_PROCESO');
    expect((float) fixture_leche()->fresh()->stock_actual)->toBe(800.0);

    $detalle = $this->actingAs($encargado)->get(route('planta.produccion.show', $lote->id));
    $detalle->assertOk();
    $detalle->assertSee('Finalizar producción');
    $detalle->assertSee('Queso Paria');

    $fila = ProduccionDetalleModel::where('lote_produccion_id', $lote->id)->firstOrFail();
    $finalizar = $this->actingAs($encargado)->post(route('planta.produccion.finalizar', $lote->id), [
        'obtenido' => [$fila->id => 19],
    ]);

    $finalizar->assertRedirect(route('planta.produccion.show', $lote->id));
    $this->assertDatabaseHas('lotes_produccion', ['id' => $lote->id, 'estado' => 'FINALIZADO']);
    $this->assertDatabaseHas('producto_movimientos', ['producto_id' => $producto->id, 'tipo' => 'INGRESO_PRODUCCION', 'cantidad' => 19]);
    expect((float) fixture_leche()->fresh()->stock_actual)->toBe(800.0);
});

it('avisa con un mensaje claro cuando falta stock y no descuenta nada', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $admin = fixture_usuario('ADMINISTRADOR');
    $azucar = fixture_insumo('Azúcar', 'kg', 6.0, 0.0, $admin->id);
    $producto = fixture_producto('Yogurt Fresa', 'unidad');
    fixture_receta($producto->id, [['insumo_id' => $azucar->id(), 'cantidad' => 1.0, 'unidad' => 'kg']], $admin->id);

    $response = $this->actingAs($encargado)->from(route('planta.produccion.index'))->post(route('planta.produccion.store'), [
        'productos' => [['producto_id' => $producto->id, 'cantidad' => 10]],
    ]);

    $response->assertRedirect(route('planta.produccion.index'));
    $response->assertSessionHas('error', fn (string $mensaje) => str_contains($mensaje, 'No hay suficiente Azúcar')
        && str_contains($mensaje, 'Se necesitan 10 kg')
        && str_contains($mensaje, 'hay 6 kg disponibles'));
    expect(LoteProduccionModel::count())->toBe(0);
});

it('un VENTAS no puede acceder al panel de planta (403)', function () {
    $ventas = fixture_usuario('VENTAS');

    $this->actingAs($ventas)->get(route('planta.produccion.index'))->assertForbidden();
});
