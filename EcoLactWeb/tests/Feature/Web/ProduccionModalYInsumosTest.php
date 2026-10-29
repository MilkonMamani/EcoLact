<?php

use App\Application\Acopio\RegistrarEntrega;
use App\Application\Produccion\CalcularRequerimientosProduccion;
use App\Domain\Produccion\InsumoRepository;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use App\Infrastructure\Persistence\Models\ProduccionDetalleModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/** Queso Paria 1 kg: 10 L leche + 5 ml cuajo + 20 g sal. */
function escenarioModal(int $userId, float $leche = 30.0): array
{
    fixture_stock_leche($leche, $userId);
    $cuajo = fixture_insumo('Cuajo', 'ml', 800.0, 0.0, $userId);
    $sal = fixture_insumo('Sal', 'kg', 50.0, 0.0, $userId);
    $queso = fixture_producto('Queso Paria 1 kg');
    fixture_receta($queso->id, [
        ['insumo_id' => $sal->id(), 'cantidad' => 20.0, 'unidad' => 'g'],
        ['insumo_id' => $cuajo->id(), 'cantidad' => 5.0, 'unidad' => 'ml'],
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 10.0, 'unidad' => 'L'],
    ], $userId);

    return [$queso, $cuajo, $sal];
}

it('el historial muestra solo los KPIs útiles, la leche disponible real y el botón que abre el modal', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_stock_leche(30.0, $admin->id);
    fixture_lote(20.0, $admin->id, 'FINALIZADO');

    $html = $this->actingAs($admin)->get(route('admin.produccion.index'))->assertOk();

    $html->assertSeeInOrder(['Lotes en proceso', 'Leche disponible', '30 L', 'Lotes finalizados']);
    $html->assertDontSee('Productos obtenidos');
    $html->assertSee('20 L');
    $html->assertSee('data-modal-open="modal-nueva-produccion"', false);
    $html->assertSeeInOrder(['Código', 'Producto(s)', 'Inicio', 'Leche utilizada', 'Resultado', 'Estado', 'Acción']);
    $html->assertDontSee('Stock de leche');
});

it('la acción de cada lote es solo Ver detalle', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_lote(10.0, $admin->id, 'EN_PROCESO');
    fixture_lote(10.0, $admin->id, 'FINALIZADO');

    $html = $this->actingAs($admin)->get(route('admin.produccion.index'))->assertOk();

    expect(substr_count($html->getContent(), 'Ver detalle del lote'))->toBeGreaterThanOrEqual(2);
    $html->assertDontSee('Continuar');
});

it('la leche utilizada del KPI sale de los movimientos de consumo, no de un valor escrito', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso] = escenarioModal($admin->id, 100.0);

    $this->actingAs($admin)->post(route('admin.produccion.store'), ['productos' => [['producto_id' => $queso->id, 'cantidad' => 3]]])->assertRedirect();

    $consumoLeche = (float) InsumoMovimientoModel::where('insumo_id', fixture_leche()->id)->where('motivo', 'CONSUMO_PRODUCCION')->sum('cantidad');
    expect($consumoLeche)->toBe(30.0);
    $this->actingAs($admin)->get(route('admin.produccion.index'))->assertSee('30 L');
});

it('el modal ofrece solo productos con receta activa, con su presentación e imagen', function () {
    Storage::fake('public');
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso] = escenarioModal($admin->id);
    $queso->update(['imagen' => 'products/queso-paria-1-kg-abcd1234.png']);
    $sinReceta = fixture_producto('Yogurt sin receta');

    $html = $this->actingAs($admin)->get(route('admin.produccion.index'))->assertOk();

    $html->assertSee('Selecciona qué fabricar. EcoLact calculará automáticamente los insumos necesarios.');
    // Planificación de varios productos: una fila inicial y el botón para agregar más (un lote por producto).
    $html->assertSee('Productos a fabricar');
    $html->assertSee('Agregar otro producto');
    $html->assertSee('data-fila-plantilla', false);
    $html->assertSee('name="productos[0][producto_id]"', false);
    $html->assertSee('name="productos[0][cantidad]"', false);
    $html->assertSee('Leche disponible');
    $html->assertSee('Queso Paria 1 kg');
    $html->assertDontSee('Yogurt sin receta');
    $html->assertSee('storage/products/queso-paria-1-kg-abcd1234.png', false);
    $html->assertSee('Observación (opcional)');
    $html->assertSee('Cancelar');
    $html->assertSee('Iniciar producción');
    $html->assertSee('Requerimientos de producción');
    $html->assertDontSee('Resumen de insumos requeridos');
    $html->assertSee('Calculando insumos...');
});

it('calcular multiplica la receta, suma varios productos y muestra la LECHE siempre primero', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso, $cuajo, $sal] = escenarioModal($admin->id, 1500.0);
    $yogur = fixture_producto('Yogurt Fresa 1 L');
    fixture_receta($yogur->id, [
        ['insumo_id' => $cuajo->id(), 'cantidad' => 1.0, 'unidad' => 'ml'],
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 1.0, 'unidad' => 'L'],
    ], $admin->id);

    $json = $this->actingAs($admin)->postJson(route('admin.produccion.requerimientos'), [
        'productos' => [['producto_id' => $queso->id, 'cantidad' => 100], ['producto_id' => $yogur->id, 'cantidad' => 50]],
    ])->assertOk();

    $insumos = $json->json('data.insumos');
    expect(array_column($insumos, 'nombre'))->toBe(['Leche', 'Cuajo', 'Sal']);
    expect($insumos[0]['necesario'])->toEqual(1050);   // 10 x 100 + 1 x 50
    expect($insumos[1]['necesario'])->toEqual(550);    // 5 x 100 + 1 x 50
    expect($insumos[2]['necesario'])->toEqual(2);      // 20 g x 100 = 2000 g = 2 kg
    expect($insumos[0]['disponible'])->toEqual(1500);
    expect($json->json('data.puede_iniciar'))->toBeTrue();
});

it('calcular es solo lectura: no crea lotes ni movimientos ni cambia stock', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso] = escenarioModal($admin->id);
    $movimientos = InsumoMovimientoModel::count();
    $stock = (float) fixture_leche()->fresh()->stock_actual;

    $this->actingAs($admin)->postJson(route('admin.produccion.requerimientos'), ['productos' => [['producto_id' => $queso->id, 'cantidad' => 2]]])->assertOk();
    $this->actingAs($admin)->get(route('admin.produccion.index'))->assertOk();

    expect(InsumoMovimientoModel::count())->toBe($movimientos);
    expect((float) fixture_leche()->fresh()->stock_actual)->toBe($stock);
    expect(LoteProduccionModel::count())->toBe(0);
});

it('con leche insuficiente el cálculo marca Insuficiente y el backend bloquea igualmente el inicio', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso] = escenarioModal($admin->id, 30.0);

    $json = $this->actingAs($admin)->postJson(route('admin.produccion.requerimientos'), ['productos' => [['producto_id' => $queso->id, 'cantidad' => 5]]])->assertOk();

    $leche = $json->json('data.insumos.0');
    expect($leche['nombre'])->toBe('Leche');
    expect($leche['suficiente'])->toBeFalse();
    expect($json->json('data.puede_iniciar'))->toBeFalse();
    expect($json->json('data.mensajes.0'))->toBe('No hay suficiente leche. Se necesitan 50 L y hay 30 L disponibles.');

    // Aunque alguien salte el botón deshabilitado, el servidor vuelve a validar todo.
    $respuesta = $this->actingAs($admin)->post(route('admin.produccion.store'), ['productos' => [['producto_id' => $queso->id, 'cantidad' => 5]]]);
    $respuesta->assertSessionHas('error', 'No hay suficiente leche. Se necesitan 50 L y hay 30 L disponibles.');
    expect(LoteProduccionModel::count())->toBe(0);
    expect((float) fixture_leche()->fresh()->stock_actual)->toBe(30.0);
});

it('el flujo del ejemplo: 30 L, 2 quesos consumen 20 L, 10 ml y 40 g y luego se finaliza con la cantidad real', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso, $cuajo, $sal] = escenarioModal($admin->id, 30.0);

    $this->actingAs($admin)->post(route('admin.produccion.store'), ['productos' => [['producto_id' => $queso->id, 'cantidad' => 2]]])->assertRedirect();

    expect((float) fixture_leche()->fresh()->stock_actual)->toBe(10.0);
    expect((float) InsumoModel::find($cuajo->id())->stock_actual)->toBe(790.0);
    expect((float) InsumoModel::find($sal->id())->stock_actual)->toBe(49.96);

    $lote = LoteProduccionModel::firstOrFail();
    expect($lote->estado)->toBe('EN_PROCESO');
    $detalleId = ProduccionDetalleModel::firstOrFail()->id;

    $this->actingAs($admin)->post(route('admin.produccion.finalizar', $lote->id), ['obtenido' => [$detalleId => 2]])->assertRedirect();

    $this->assertDatabaseHas('producto_movimientos', ['producto_id' => $queso->id, 'tipo' => 'INGRESO_PRODUCCION', 'cantidad' => 2]);
    expect((float) fixture_leche()->fresh()->stock_actual)->toBe(10.0);
});

it('Insumos: acciones separadas en el encabezado, estado de existencias y la leche primera con "Del sistema"', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_stock_leche(30.0, $admin->id);
    fixture_insumo('Azúcar', 'kg', 5.0, 0.0, $admin->id);
    fixture_insumo('Cuajo', 'ml', 800.0, 100.0, $admin->id);
    fixture_insumo('Aaa primer alfabético', 'g', 10.0, 0.0, $admin->id);

    $html = $this->actingAs($admin)->get(route('admin.insumos.index'))->assertOk();

    $html->assertSee('Administra las existencias y materiales utilizados en la producción.');
    // Crear insumo, comprar y escanear son acciones distintas; ajustar va en cada fila y en el panel.
    // Las compras se registran en Compras y gastos: Insumos solo enlaza con «Comprar ...».
    $html->assertSeeInOrder(['Nuevo insumo', 'Ajustar stock de']);
    $html->assertDontSee('Ver movimientos');
    $html->assertDontSee('Escanear boleta');
    $html->assertSeeInOrder(['insumos', 'con stock', 'con stock bajo', 'sin stock']);
    $html->assertSeeInOrder(['Insumo', 'Categoría', 'Unidad', 'Stock disponible', 'Stock mínimo', 'Estado', 'Acciones']);
    $html->assertSeeInOrder(['Leche', 'Del sistema', 'Materia prima', '30 L', 'Disponible', 'Aaa primer alfabético', 'Azúcar', 'Cuajo']);
});

it('Insumos: la leche no ofrece Desactivar y un insumo normal sí', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $cuajo = fixture_insumo('Cuajo', 'ml', 800.0, 0.0, $admin->id);

    $leche = $this->actingAs($admin)->get(route('admin.insumos.panel', fixture_leche()->id))->assertOk();
    $leche->assertDontSee('Desactivar Leche');
    $leche->assertSee('Editar Leche');

    $this->actingAs($admin)->get(route('admin.insumos.panel', $cuajo->id()))->assertOk()->assertSee('Desactivar Cuajo');
    // ?insumo=ID abre la pantalla con ese panel ya cargado.
    $this->actingAs($admin)->get(route('admin.insumos.index', ['insumo' => $cuajo->id()]))->assertOk()->assertSee('Desactivar Cuajo');
});

it('no se puede crear otra leche (por nombre en cualquier mayúscula) ni desactivar la del sistema', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    foreach (['Leche', 'LECHE', ' leche '] as $nombre) {
        $this->actingAs($admin)->post(route('admin.insumos.store'), ['nombre' => $nombre, 'tipo' => 'MATERIA_PRIMA', 'unidad_base' => 'L', 'stock_minimo' => 0]);
    }

    expect(InsumoModel::whereRaw('LOWER(TRIM(nombre)) = ?', ['leche'])->count())->toBe(1);
    expect(InsumoModel::where('codigo', 'LECHE')->count())->toBe(1);
});

it('el ingreso de leche por una entrega aparece como movimiento y sube el stock que ve Producción', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    app(RegistrarEntrega::class)->execute('DNI', $proveedor->dni, $acopiador->user_id, 20.0);

    $this->actingAs($admin)->get(route('admin.produccion.index'))->assertSeeInOrder(['Leche disponible', '20 L']);
    $this->assertDatabaseHas('insumo_movimientos', ['insumo_id' => fixture_leche()->id, 'motivo' => 'INGRESO_ACOPIO', 'origen_tipo' => 'ENTREGA']);
});

it('el requerimiento con leche insuficiente usa la misma redacción en el modal y en el backend', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso] = escenarioModal($admin->id, 30.0);

    $req = app(CalcularRequerimientosProduccion::class)->execute([['producto_id' => $queso->id, 'cantidad' => 5]]);

    expect($req->mensajesDeBloqueo())->toBe(['No hay suficiente leche. Se necesitan 50 L y hay 30 L disponibles.']);
});

it('el modal "Nuevo insumo" tiene las secciones, campos y textos del diseño', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $html = $this->actingAs($admin)->get(route('admin.insumos.index'))->assertOk();

    $html->assertSee('id="modal-nuevo-insumo"', false);
    $html->assertSeeInOrder([
        'Nuevo insumo', 'Crea la ficha del insumo. Todavía no registra ninguna compra.', 'Información general',
        'Nombre del insumo', 'Tipo', 'Descripción (opcional)',
        '¿Cómo se mide este insumo?', 'Por peso', 'kg o g', 'Por volumen', 'L o ml', 'Por unidades', 'und',
        'Kilogramos (kg)', 'Gramos (g)', 'Litros (L)', 'Mililitros (ml)', 'Unidades (und)',
        'Stock mínimo', 'Estado', 'El stock empieza en 0.', 'Cancelar', 'Crear insumo',
    ]);
    // Ninguna unidad viene elegida de antemano: la persona decide cómo se mide.
    expect(preg_match_all('/<input[^>]*name="unidad_base"[^>]*>/', $html->getContent(), $radios))->toBeGreaterThan(0);
    expect(array_filter($radios[0], fn (string $radio): bool => preg_match('/\schecked\b/', $radio) === 1))->toBe([]);
    $html->assertSee('data-sufijo-unidad', false);
    // Crear la ficha no es una compra: no se pide stock inicial.
    $html->assertDontSee('name="stock_inicial"', false);
});

it('crear un insumo desde el modal guarda descripción, unidad y mínimo, con stock 0', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->post(route('admin.insumos.store'), [
        'nombre' => 'Cuajo', 'tipo' => 'INSUMO', 'unidad_base' => 'ml', 'estado' => 'ACTIVO',
        'descripcion' => 'Coagulante utilizado para quesos', 'stock_minimo' => 100,
    ])->assertSessionHas('success');

    $cuajo = InsumoModel::where('nombre', 'Cuajo')->firstOrFail();
    expect($cuajo->descripcion)->toBe('Coagulante utilizado para quesos');
    expect((float) $cuajo->stock_actual)->toBe(0.0);
    expect((float) $cuajo->stock_minimo)->toBe(100.0);
});

it('un error al guardar reabre el modal con lo escrito', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->from(route('admin.insumos.index'))->post(route('admin.insumos.store'), [
        'nombre' => 'Cuajo', 'tipo' => 'INSUMO', 'unidad_base' => 'ml',
    ])->assertRedirect(route('admin.insumos.index'));

    $this->actingAs($admin)->get(route('admin.insumos.index'))->assertSee('data-reabrir-nuevo', false)->assertSee('value="Cuajo"', false);
});

it('"Stock bajo" aparece cuando el stock LLEGA al mínimo configurado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_insumo('Cuajo', 'ml', 100.0, 100.0, $admin->id);
    fixture_insumo('Sal', 'kg', 101.0, 100.0, $admin->id);

    $html = $this->actingAs($admin)->get(route('admin.insumos.index', ['nivel' => 'bajo']))->assertOk();

    $html->assertSee('Cuajo');
    $html->assertDontSee('Sal fina');
    expect(collect(app(InsumoRepository::class)->listarBajoMinimo())->map->nombre()->all())->toBe(['Cuajo']);
});
