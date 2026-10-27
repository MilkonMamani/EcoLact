<?php

use App\Domain\Produccion\ProductoStockRepository;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use App\Infrastructure\Persistence\Models\ProduccionDetalleModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\ProductoMovimientoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

// Estas pruebas no tratan de asistencia: el personal ya tiene su jornada PRESENTE de hoy.
beforeEach(fn () => fixture_personal_con_asistencia_hoy());

/** Queso Paria · 1 kg: 10 L leche + 15 ml cuajo + 30 g sal por unidad. */
function quesoParia(int $userId, float $leche = 172.0, float $sal = 1000.0): ProductoModel
{
    fixture_stock_leche($leche, $userId);
    $cuajo = fixture_insumo('Cuajo', 'ml', 300.0, 0.0, $userId);
    $salInsumo = fixture_insumo('Sal', 'g', $sal, 0.0, $userId);
    $queso = fixture_producto('Queso Paria 1 kg');
    $queso->update(['nombre_base' => 'Queso Paria', 'presentacion_cantidad' => 1, 'presentacion_unidad' => 'kg']);
    fixture_receta($queso->id, [
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 10.0, 'unidad' => 'L'],
        ['insumo_id' => $cuajo->id(), 'cantidad' => 15.0, 'unidad' => 'ml'],
        ['insumo_id' => $salInsumo->id(), 'cantidad' => 30.0, 'unidad' => 'g'],
    ], $userId);

    return $queso;
}

function stockProducto(int $productoId): float
{
    return app(ProductoStockRepository::class)->buscarPorProductoId($productoId)?->cantidadActual() ?? 0.0;
}

it('el requerimiento muestra el producto con su presentación y receta x cantidad', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $queso = quesoParia($encargado->id);

    $json = $this->actingAs($encargado)->postJson(route('planta.produccion.requerimientos'), [
        'productos' => [['producto_id' => $queso->id, 'cantidad' => 10]],
    ])->assertOk();

    expect($json->json('data.lineas.0.etiqueta'))->toBe('Queso Paria · 1 kg');
    expect(array_column($json->json('data.insumos'), 'necesario', 'nombre'))->toEqual(['Leche' => 100, 'Cuajo' => 150, 'Sal' => 300]);
    expect($json->json('data.puede_iniciar'))->toBeTrue();
    expect($json->json('data.resumen'))->toBe('Todos los insumos están disponibles.');
    expect($json->json('data.insumos.0.es_leche'))->toBeTrue();
});

it('si falta sal bloquea con un mensaje claro y no descuenta nada', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $queso = quesoParia($encargado->id, sal: 250.0);

    $json = $this->actingAs($encargado)->postJson(route('planta.produccion.requerimientos'), [
        'productos' => [['producto_id' => $queso->id, 'cantidad' => 10]],
    ])->assertOk();

    expect($json->json('data.puede_iniciar'))->toBeFalse();
    expect($json->json('data.mensajes'))->toBe(['No hay suficiente Sal. Se necesitan 300 g y hay 250 g disponibles.']);
    expect($json->json('data.resumen'))->toBe('No hay suficientes insumos para iniciar esta producción.');
    expect(array_column($json->json('data.insumos'), 'faltante', 'nombre'))->toEqual(['Leche' => 0, 'Cuajo' => 0, 'Sal' => 50]);

    $this->actingAs($encargado)->post(route('planta.produccion.store'), ['productos' => [['producto_id' => $queso->id, 'cantidad' => 10]]])
        ->assertSessionHas('error', 'No hay suficiente Sal. Se necesitan 300 g y hay 250 g disponibles.');
    expect(LoteProduccionModel::count())->toBe(0);
    expect((float) fixture_leche()->fresh()->stock_actual)->toBe(172.0);
});

it('iniciar descuenta insumos una sola vez, no suma producto y avisa con el código del lote', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $queso = quesoParia($encargado->id);

    $respuesta = $this->actingAs($encargado)->post(route('planta.produccion.store'), ['productos' => [['producto_id' => $queso->id, 'cantidad' => 10]]]);

    $lote = LoteProduccionModel::firstOrFail();
    $respuesta->assertSessionHas('success', "Producción {$lote->codigo} iniciada. Los insumos fueron descontados.");
    expect($lote->estado)->toBe('EN_PROCESO');
    expect((float) fixture_leche()->fresh()->stock_actual)->toBe(72.0);
    expect((float) InsumoModel::where('nombre', 'Sal')->value('stock_actual'))->toBe(700.0);
    expect(InsumoMovimientoModel::where('motivo', 'CONSUMO_PRODUCCION')->count())->toBe(3);
    expect(stockProducto($queso->id))->toBe(0.0);

    $this->actingAs($encargado)->get(route('planta.produccion.index'))
        ->assertSeeInOrder([$lote->codigo, 'Queso Paria · 1 kg', '100 L', 'Pendiente']);
});

it('finalizar con planificado 10 y obtenido 9 guarda merma 1, la observación y suma 9 al stock', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $queso = quesoParia($encargado->id);
    $this->actingAs($encargado)->post(route('planta.produccion.store'), ['productos' => [['producto_id' => $queso->id, 'cantidad' => 10]]]);
    $lote = LoteProduccionModel::firstOrFail();
    $detalle = ProduccionDetalleModel::firstOrFail();

    $this->actingAs($encargado)->get(route('planta.produccion.show', $lote->id))
        ->assertSeeInOrder(['Impacto en inventario', 'Stock actual', '0 unidades']);

    $respuesta = $this->actingAs($encargado)->post(route('planta.produccion.finalizar', $lote->id), [
        'obtenido' => [$detalle->id => 9],
        'observacion' => 'Se perdió una unidad durante el moldeado.',
    ]);

    $respuesta->assertSessionHas('success', "Producción {$lote->codigo} finalizada. Se agregaron 9 unidades al stock de Queso Paria · 1 kg.");
    expect((float) $detalle->fresh()->cantidad_obtenida)->toBe(9.0);
    expect((float) $detalle->fresh()->merma)->toBe(1.0);
    expect($lote->fresh()->observacion_cierre)->toBe('Se perdió una unidad durante el moldeado.');
    expect($lote->fresh()->fecha_hora_fin)->not->toBeNull();
    expect(stockProducto($queso->id))->toBe(9.0);
    expect((float) fixture_leche()->fresh()->stock_actual)->toBe(72.0);

    $this->actingAs($encargado)->get(route('planta.produccion.show', $lote->id))
        ->assertSeeInOrder(['Producción finalizada', 'Planificado', '10 unidades', 'Obtenido', '9 unidades', 'Merma', '1 unidad', 'Stock incorporado', '+9 unidades'])
        ->assertSee('Se perdió una unidad durante el moldeado.')
        ->assertDontSee('data-produccion-finalizar', false);
});

it('si el producto ya tenía stock, finalizar suma lo obtenido (20 + 9 = 29)', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $queso = quesoParia($encargado->id);
    ProductoMovimientoModel::create([
        'producto_id' => $queso->id, 'tipo' => 'AJUSTE_ENTRADA', 'cantidad' => 20, 'fecha' => now(),
        'registrado_por_user_id' => $encargado->id, 'observacion' => 'Stock previo',
    ]);
    $this->actingAs($encargado)->post(route('planta.produccion.store'), ['productos' => [['producto_id' => $queso->id, 'cantidad' => 10]]]);
    $lote = LoteProduccionModel::firstOrFail();

    $this->actingAs($encargado)->get(route('planta.produccion.show', $lote->id))->assertSeeInOrder(['Stock actual', '20 unidades']);

    $this->actingAs($encargado)->post(route('planta.produccion.finalizar', $lote->id), ['obtenido' => [ProduccionDetalleModel::firstOrFail()->id => 9]]);

    expect(stockProducto($queso->id))->toBe(29.0);
});

it('un segundo envío de finalizar se rechaza y no duplica el stock', function () {
    $encargado = fixture_usuario('ENCARGADO_PLANTA');
    $queso = quesoParia($encargado->id);
    $this->actingAs($encargado)->post(route('planta.produccion.store'), ['productos' => [['producto_id' => $queso->id, 'cantidad' => 10]]]);
    $lote = LoteProduccionModel::firstOrFail();
    $datos = ['obtenido' => [ProduccionDetalleModel::firstOrFail()->id => 9]];

    $this->actingAs($encargado)->post(route('planta.produccion.finalizar', $lote->id), $datos)->assertSessionHas('success');
    $this->actingAs($encargado)->post(route('planta.produccion.finalizar', $lote->id), $datos)->assertSessionHas('error', 'Este lote ya fue finalizado.');

    expect(stockProducto($queso->id))->toBe(9.0);
    expect(ProductoMovimientoModel::where('lote_produccion_id', $lote->id)->count())->toBe(1);
});
