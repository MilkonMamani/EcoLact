<?php

use App\Application\Pago\ComprobantePagoProveedor;
use App\Application\Pago\GenerarPagosPendientes;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\PagoModel;
use App\Infrastructure\Persistence\Models\PeriodoLiquidacionModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-09-20 10:00:00');
});

/**
 * Proveedor con una entrega ACEPTADA y otra OBSERVADA en la semana del 10 al 16 de septiembre.
 * Deja precio S/ 2.10, observada -0.30 por litro y genera el pago pendiente.
 *
 * @return array{0: User, 1: ObligacionPagoModel}
 */
function pago_semanal_con_pendiente(string $nombreZona = 'Zona 1'): array
{
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin);
    pagos_configurar('PROVEEDOR', null, '2026-01-01', $admin);

    $proveedor = fixture_proveedor(fixture_zona($nombreZona), '01345678');
    pagos_entrega($proveedor, 100.0, '2026-09-10', 'ACEPTADA');
    pagos_entrega($proveedor, 50.0, '2026-09-11', 'OBSERVADA');
    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'), $admin->id);

    return [$admin, ObligacionPagoModel::firstOrFail()];
}

function pago_semanal_compra_descontada(User $admin, ObligacionPagoModel $obligacion, float $precio, float $cantidad): void
{
    $periodo = PeriodoLiquidacionModel::create(['fecha_inicio' => '2026-09-10', 'fecha_fin' => '2026-09-16', 'estado' => 'ABIERTO']);
    $producto = ProductoModel::create(['nombre' => 'Queso Paria 500 g', 'nombre_base' => 'Queso Paria', 'presentacion_cantidad' => 500, 'presentacion_unidad' => 'g', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);

    VentaProductoModel::create([
        'producto_id' => $producto->id, 'proveedor_id' => $obligacion->proveedor_id,
        'tipo_cliente' => 'PROVEEDOR', 'cantidad' => $cantidad, 'precio_unitario' => $precio, 'importe_total' => $precio * $cantidad,
        'forma_pago' => 'DESCUENTO_PLANILLA', 'periodo_id' => $periodo->id, 'estado' => 'REGISTRADA',
        'fecha' => '2026-09-11', 'registrado_por_user_id' => $admin->id,
    ]);
}

it('el sidebar agrupa Pagos con Pagar a proveedores y Pagar a trabajadores', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->get(route('admin.pagos.proveedores.index'))
        ->assertOk()
        ->assertSeeInOrder(['Pagos', 'Pagar a proveedores', 'Pagar a trabajadores'])
        ->assertSee(route('admin.pagos.trabajadores.index'));
});

it('pagar a trabajadores muestra la pantalla de tabla y panel lateral', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->get(route('admin.pagos.trabajadores.index'))
        ->assertOk()
        ->assertSee('Pagos a trabajadores')
        ->assertSee('Gestiona los pagos individuales de los trabajadores correspondientes al mes seleccionado.')
        ->assertSee('data-fichas-panel', false);
});

it('la pantalla muestra KPIs, filtros, tabla con el cálculo y la hoja individual', function () {
    [$admin] = pago_semanal_con_pendiente();

    $this->actingAs($admin)->get(route('admin.pagos.proveedores.index'))
        ->assertOk()
        ->assertSeeInOrder(['Pago semanal a proveedores', 'Pendientes de pago', 'Litros pagables', 'Total pendiente', 'Seleccionados'])
        ->assertSeeInOrder(['Semana', 'Zona', 'Estado', 'Seleccionar pendientes de la zona'])
        ->assertSee('Se imprimirá 1 hoja por cada proveedor seleccionado.')
        ->assertSee('Proveedores de la semana')
        ->assertSeeInOrder(['Proveedor', 'Zona', 'Litros', 'Precio/L', 'Calidad', 'Compras', 'Subtotal', 'Total', 'Estado'])
        ->assertSee('Hoja individual del proveedor')
        ->assertSee('Revisar e imprimir')
        ->assertSee('Limpiar selección')
        ->assertDontSee('Generar PDF de sobres')
        ->assertDontSee('Resumen de selección')
        ->assertSee('Vista previa de impresión')
        ->assertSee('Zona 1')
        ->assertSee('150 L')
        ->assertSee('S/ 2.10')
        ->assertSee('- S/ 15.00')   // 50 L observados × -0.30
        ->assertSee('S/ 300.00')    // 150 × 2.10 - 15 = subtotal
        ->assertSee('Pendiente');
});

it('el comprobante calcula litros, penalidad, compras descontadas y total final', function () {
    [$admin, $obligacion] = pago_semanal_con_pendiente();
    pago_semanal_compra_descontada($admin, $obligacion, 18.0, 2);
    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'), $admin->id);

    $respuesta = $this->actingAs($admin)->getJson(route('admin.pagos.proveedores.preview', ['ids' => [$obligacion->id]]))
        ->assertOk()
        ->assertJsonCount(1, 'hojas');

    expect((float) $respuesta->json('hojas.0.total'))->toBe(264.0);

    $html = $respuesta->json('hojas.0.html');
    expect($html)->toContain('Comprobante de pago')
        ->and($html)->toContain('01345678')
        ->and($html)->toContain('PRV-'.str_pad((string) $obligacion->proveedor_id, 3, '0', STR_PAD_LEFT))
        ->and($html)->toContain('<dt>Semana</dt><dd>38</dd>')
        ->and($html)->toContain('10/09/2026 – 16/09/2026')
        ->and($html)->toContain('Penalidad por calidad')
        ->and($html)->toContain('− S/ 15.00')
        ->and($html)->toContain('Subtotal por leche')
        ->and($html)->toContain('S/ 300.00')
        ->and($html)->toContain('Queso Paria')
        ->and($html)->toContain('500 g')
        ->and($html)->toContain('− S/ 36.00')
        ->and($html)->toContain('Total a entregar')
        ->and($html)->toContain('Forma de pago')
        ->and($html)->toContain('S/ 264.00')
        ->and($html)->toContain('Recibí conforme')
        ->and($html)->toContain('Entregado por EcoLact');
});

it('el documento conserva los importes y describe la calidad y las compras', function (float $calidad, string $concepto, int $compras, float $subtotal, float $descuentos, float $total) {
    $c = new ComprobantePagoProveedor(
        obligacionId: 42, proveedorId: 12, proveedorNombre: 'Miguel Flores Apaza',
        dni: '72100011', codigo: 'PRV-012', zona: 'Collana 2', semanaEtiqueta: 'Semana 39',
        periodoInicio: '2026-09-17', periodoFin: '2026-09-23', rango: '17/09/2026 - 23/09/2026',
        litros: 30, precioLitro: 1.70, importeLeche: 51, penalidad: $calidad,
        penalidadLitros: $calidad, detallePenalidad: 'Según análisis de calidad', subtotalLeche: $subtotal,
        descuentos: array_fill(0, $compras, ['producto' => 'Queso Paria', 'presentacion' => '500 g', 'cantidad' => '1', 'precioUnitario' => 5.0, 'importe' => 5.0]),
        subtotalDescuentos: $descuentos, totalFinal: $total, estado: 'PENDIENTE', pagadoEl: null, anioFirma: 2026,
    );

    $html = view('admin.pagos.proveedores._comprobante', ['c' => $c])->render();

    expect($html)->toContain($concepto, 'PRV-2026-39-0042', '72100011', 'Recibí conforme', 'Entregado por EcoLact')
        ->toContain('data-hoja-total>S/ '.number_format($total, 2))
        ->not->toContain('1. Datos', 'pp-icono', 'Productores de hoy');
    expect(substr_count($html, '<td>Queso Paria</td>'))->toBe($compras);
    expect(str_contains($html, 'data-sin-compras'))->toBe($compras === 0);
    expect(str_contains($html, '<td>'.$concepto.'</td>'))->toBe($calidad !== 0.0);
})->with([
    'sin calidad ni compras' => [0.0, 'Sin bonificación ni penalidad', 0, 51.0, 0.0, 51.0],
    'penalidad y una compra' => [-1.7, 'Penalidad por calidad', 1, 49.3, 5.0, 44.3],
    'bonificación y varias compras' => [1.7, 'Bonificación por calidad', 3, 52.7, 15.0, 37.7],
    'muchas compras conservan todas las filas' => [0.0, 'Sin bonificación ni penalidad', 10, 51.0, 50.0, 1.0],
]);

it('el listado entrega el DNI para buscar y la misma identidad en el comprobante', function () {
    [$admin, $obligacion] = pago_semanal_con_pendiente();

    $respuesta = $this->actingAs($admin)->get(route('admin.pagos.proveedores.index', ['buscar' => '01345678']));

    expect($respuesta->viewData('datos')['filas'][0]['dni'])->toBe('01345678');
    $respuesta->assertSee('Buscar proveedor por nombre, DNI o zona');
    $this->get(route('admin.pagos.proveedores.receipt', $obligacion->id))
        ->assertSee('01345678')->assertSee('Comprobante de pago');
});

it('la vista previa múltiple entrega un documento por proveedor sin registrar pagos', function () {
    [$admin, $primera] = pago_semanal_con_pendiente();
    $proveedor = fixture_proveedor(fixture_zona('Zona 2'), '72100012');
    pagos_entrega($proveedor, 100.0, '2026-09-10', 'ACEPTADA');
    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'), $admin->id);
    $segunda = ObligacionPagoModel::where('proveedor_id', $proveedor->id)->firstOrFail();

    $respuesta = $this->actingAs($admin)->getJson(route('admin.pagos.proveedores.preview', ['ids' => [$primera->id, $segunda->id]]));

    $respuesta->assertJsonCount(2, 'hojas');
    expect(array_column($respuesta->json('hojas'), 'id'))->toBe([$primera->id, $segunda->id]);
    foreach ($respuesta->json('hojas') as $hoja) {
        expect(substr_count($hoja['html'], '<article class="pp-hoja"'))->toBe(1);
    }
    expect(PagoModel::count())->toBe(0);
    expect($primera->fresh()->estado)->toBe('PENDIENTE');
    expect($segunda->fresh()->estado)->toBe('PENDIENTE');
});

it('abrir la vista previa no marca nada como pagado', function () {
    [$admin, $obligacion] = pago_semanal_con_pendiente();

    $this->actingAs($admin)->getJson(route('admin.pagos.proveedores.preview', ['ids' => [$obligacion->id]]))->assertOk();

    expect($obligacion->fresh()->estado)->toBe('PENDIENTE')->and(PagoModel::count())->toBe(0);
});

it('imprimir y marcar pagados registra el pago en efectivo con usuario, fecha, lote y copia del comprobante', function () {
    [$admin, $obligacion] = pago_semanal_con_pendiente();

    $this->actingAs($admin)->post(route('admin.pagos.proveedores.print-and-pay'), [
        'ids' => [$obligacion->id],
        'montos' => [$obligacion->id => '300.00'],
    ])->assertRedirect()->assertSessionHas('success');

    $pago = PagoModel::firstOrFail();

    expect($obligacion->fresh()->estado)->toBe('PAGADO')
        ->and($pago->metodo_pago)->toBe('EFECTIVO')
        ->and($pago->registrado_por_user_id)->toBe($admin->id)
        ->and($pago->pagado_at)->not->toBeNull()
        ->and($pago->impreso_at)->not->toBeNull()
        ->and($pago->lote_codigo)->toStartWith('PP-')
        ->and((float) $pago->monto)->toBe(300.0)
        ->and($pago->comprobante_snapshot['proveedorNombre'])->toBe($obligacion->beneficiario_nombre);

    // Con el filtro Pendientes ya no cumple el estado; con Todos (el valor por defecto) sigue visible como Pagado.
    $this->actingAs($admin)->get(route('admin.pagos.proveedores.index', ['estado' => 'pendientes']))
        ->assertOk()
        ->assertDontSee($obligacion->beneficiario_nombre);

    $this->actingAs($admin)->get(route('admin.pagos.proveedores.index'))
        ->assertOk()
        ->assertSee($obligacion->beneficiario_nombre)
        ->assertSee('Pagado');
});

it('los ya pagados dejan de salir como pendientes y aparecen con el filtro Pagados', function () {
    [$admin, $obligacion] = pago_semanal_con_pendiente();
    $this->actingAs($admin)->post(route('admin.pagos.proveedores.print-and-pay'), ['ids' => [$obligacion->id]]);

    $this->actingAs($admin)->get(route('admin.pagos.proveedores.index', ['semana' => '2026-09-10', 'estado' => 'pendientes']))
        ->assertOk()
        ->assertSee('No hay proveedores para esta semana y estos filtros');

    $this->actingAs($admin)->get(route('admin.pagos.proveedores.index', ['semana' => '2026-09-10', 'estado' => 'pagados']))
        ->assertOk()
        ->assertSee($obligacion->beneficiario_nombre)
        ->assertSee('Pagado');
});

it('no paga si el monto cambió desde la vista previa y no deja pagos a medias', function () {
    [$admin, $obligacion] = pago_semanal_con_pendiente();

    $this->actingAs($admin)->post(route('admin.pagos.proveedores.print-and-pay'), [
        'ids' => [$obligacion->id],
        'montos' => [$obligacion->id => '999.00'],
    ])->assertRedirect()->assertSessionHas('error');

    expect($obligacion->fresh()->estado)->toBe('PENDIENTE')->and(PagoModel::count())->toBe(0);
});

it('un pago ya realizado no se puede pagar otra vez', function () {
    [$admin, $obligacion] = pago_semanal_con_pendiente();
    $this->actingAs($admin)->post(route('admin.pagos.proveedores.print-and-pay'), ['ids' => [$obligacion->id]]);

    $this->actingAs($admin)->post(route('admin.pagos.proveedores.print-and-pay'), ['ids' => [$obligacion->id]])
        ->assertSessionHas('error');

    expect(PagoModel::count())->toBe(1);
});

it('exige seleccionar al menos un proveedor', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $this->actingAs($admin)->post(route('admin.pagos.proveedores.print-and-pay'), [])->assertSessionHasErrors('ids');
});

it('el comprobante de un pago realizado usa la copia guardada, no los datos actuales', function () {
    [$admin, $obligacion] = pago_semanal_con_pendiente();
    $this->actingAs($admin)->post(route('admin.pagos.proveedores.print-and-pay'), ['ids' => [$obligacion->id]]);

    $obligacion->proveedor->zona->update(['nombre' => 'Zona renombrada']);

    $this->actingAs($admin)->get(route('admin.pagos.proveedores.receipt', $obligacion))
        ->assertOk()
        ->assertSee('Pago semanal a proveedor')
        ->assertSee('Pagado el')
        ->assertDontSee('Zona renombrada');
});

it('solo el administrador puede pagar a proveedores', function () {
    $proveedor = fixture_usuario('PROVEEDOR');
    $ruta = route('admin.pagos.proveedores.index');

    $this->actingAs($proveedor)->get($ruta)->assertForbidden();
    $this->actingAs($proveedor)->post(route('admin.pagos.proveedores.print-and-pay'), ['ids' => [1]])->assertForbidden();
});

it('toda entrega de Acopio aparece en Pagar a proveedores aunque su pago no se haya sincronizado al registrarla', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin);
    pagos_configurar('PROVEEDOR', null, '2026-01-01', $admin);
    $zona = fixture_zona('Zona 1');
    $rosa = fixture_proveedor($zona, '01345678');
    $rosa->update(['nombres' => 'Rosa', 'apellidos' => 'Quispe']);
    $pedro = fixture_proveedor($zona, '01345679');
    $pedro->update(['nombres' => 'Pedro', 'apellidos' => 'Apaza']);

    // Entregas guardadas sin pasar por la sincronización (p. ej. registradas antes de configurar el pago).
    $acopiador = fixture_acopiador($zona);
    fixture_entrega($rosa, $acopiador, 40.0, new DateTimeImmutable('2026-09-18 08:00:00'));
    fixture_entrega($pedro, $acopiador, 25.0, new DateTimeImmutable('2026-09-19 08:00:00'));
    expect(ObligacionPagoModel::count())->toBe(0);

    $this->actingAs($admin)->get(route('admin.pagos.proveedores.index'))
        ->assertOk()
        ->assertSee('Rosa Quispe')
        ->assertSee('Pedro Apaza');

    expect(ObligacionPagoModel::where('estado', 'PENDIENTE')->count())->toBe(2);
});
