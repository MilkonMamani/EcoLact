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
        ->assertSee('Revisa los pagos mensuales del personal y registra la entrega correspondiente.')
        ->assertSee('data-fichas-panel', false);
});

it('la pantalla muestra métricas compactas, filtros, la tabla de liquidaciones y el panel Comprobante de pago', function () {
    [$admin] = pago_semanal_con_pendiente();

    $html = $this->actingAs($admin)->get(route('admin.pagos.proveedores.index'))
        ->assertOk()
        ->assertSeeInOrder(['Pago semanal a proveedores', 'Revisa las liquidaciones semanales y confirma los pagos correspondientes.'])
        ->assertSeeInOrder(['Pendientes', '1 proveedor', 'Litros pagables', '150 L', 'Total pendiente', 'S/ 270.00'])
        ->assertSeeInOrder(['Buscar proveedor...', 'filtro-semana', 'filtro-zona', 'filtro-estado', 'Filtrar', 'Limpiar'], false)
        ->assertSeeInOrder(['Proveedor', 'Zona', 'Litros', 'Precio/L', 'Calidad semanal', 'Compras', 'Total', 'Estado'])
        ->assertSeeInOrder(['Comprobante de pago', 'Vista previa del comprobante del proveedor seleccionado.', 'Selecciona un proveedor', 'Haz clic en una fila para revisar su liquidación y comprobante.'])
        ->assertSee('Revisar seleccionados')
        ->assertSee('Seleccionar pendientes visibles')
        ->assertSee('Imprimir comprobante')
        ->assertSee('Registrar pago')
        ->assertSee('Vista previa de impresión')
        ->assertDontSee('Hoja individual del proveedor')
        ->assertDontSee('Revisar e imprimir')
        ->assertDontSee('Seleccionados:')
        ->getContent();

    preg_match('/<thead>.*?<\/thead>/s', $html, $cabecera);
    preg_match('/<tr data-fila .*?<\/tr>/s', $html, $fila);

    // Semana OBSERVADA: 150 L a 2.10 - 0.30 = 1.80; el total ya es el monto a entregar.
    expect($cabecera[0])->not->toContain('Subtotal')
        ->and($fila[0])->toContain('Semana 38')->toContain('150 L')->toContain('S/ 1.80')->toContain('Base S/ 2.10')
        ->toContain('ajuste −S/ 0.30')->toContain('Observada')->toContain('S/ 270.00')->toContain('Pendiente')
        ->toContain('title="Ver comprobante"')->not->toContain('eye');
});

it('el comprobante explica el cálculo semanal: litros, precio base, resultado, penalización, precio aplicado, compras y total', function () {
    [$admin, $obligacion] = pago_semanal_con_pendiente();
    pago_semanal_compra_descontada($admin, $obligacion, 18.0, 2);
    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'), $admin->id);

    $respuesta = $this->actingAs($admin)->getJson(route('admin.pagos.proveedores.preview', ['ids' => [$obligacion->id]]))
        ->assertOk()
        ->assertJsonCount(1, 'hojas');

    expect((float) $respuesta->json('hojas.0.total'))->toBe(234.0);

    $html = $respuesta->json('hojas.0.html');
    $ordenado = fn (array $partes): bool => array_reduce($partes, fn (int|false $desde, string $parte) => $desde === false ? false : (($pos = strpos($html, $parte, $desde)) === false ? false : $pos + strlen($parte)), 0) !== false;

    expect($html)->toContain('EcoLact')->toContain('Comprobante de pago')
        ->toContain('01345678')->toContain('Zona 1')
        ->toContain('PRV-'.str_pad((string) $obligacion->proveedor_id, 3, '0', STR_PAD_LEFT))
        ->toContain('<dt>Semana</dt><dd>38</dd>')
        ->toContain('10/09/2026 – 16/09/2026')
        ->toContain('Fecha de emisión')
        ->toContain('<dd data-hoja-estado>Pendiente</dd>')
        ->toContain('La penalización se aplica a todos los litros de la semana.')
        ->toContain('Compras en planta')->toContain('Queso Paria')->toContain('500 g')
        ->toContain('Firma del proveedor')->toContain('Firma autorizada')
        ->not->toContain('Subtotal por leche');
    expect($ordenado([
        'Litros de la semana', '150 L', 'Precio base', 'S/ 2.10 / L', 'Resultado semanal', 'Observada',
        'Penalización semanal', '− S/ 0.30 / L', 'Precio aplicado', 'S/ 1.80 / L', 'Importe por leche', 'S/ 270.00',
        'Descuentos', 'Total a pagar', 'S/ 234.00',
    ]))->toBeTrue();
});

it('el documento muestra cada resultado semanal con su precio y solo lista descuentos si hubo compras', function (?string $resultado, float $ajuste, float $aplicado, int $compras, string $estado, array $contiene, array $noContiene) {
    $c = new ComprobantePagoProveedor(
        obligacionId: 42, proveedorId: 12, proveedorNombre: 'Miguel Flores Apaza',
        dni: '72100011', codigo: 'PRV-012', zona: 'Collana 2', semanaEtiqueta: 'Semana 39',
        periodoInicio: '2026-09-17', periodoFin: '2026-09-23', rango: '17/09/2026 - 23/09/2026',
        litros: $resultado === 'RECHAZADA' ? 0 : 30, precioLitro: 1.70, importeLeche: $resultado === 'RECHAZADA' ? 0 : 51,
        penalidad: round($ajuste * 30, 2), penalidadLitros: 0, detallePenalidad: '', subtotalLeche: round($aplicado * 30, 2),
        descuentos: array_fill(0, $compras, ['producto' => 'Queso Paria', 'presentacion' => '500 g', 'cantidad' => '1', 'precioUnitario' => 5.0, 'importe' => 5.0]),
        subtotalDescuentos: $compras * 5.0, totalFinal: max(0, round($aplicado * 30 - $compras * 5.0, 2)), estado: $estado, pagadoEl: null, anioFirma: 2026,
        resultadoSemanal: $resultado, litrosSemana: 30, ajusteLitro: $ajuste, precioAplicado: $aplicado,
    );

    $html = view('admin.pagos.proveedores._comprobante', ['c' => $c])->render();

    expect($html)->toContain('PRV-2026-39-0042', '72100011', 'Firma del proveedor', 'Firma autorizada', ...$contiene)
        ->not->toContain(...$noContiene);
    expect(substr_count($html, '<td>Queso Paria</td>'))->toBe($compras);
})->with([
    'aceptada sin compras' => ['ACEPTADA', 0.0, 1.70, 0, 'PENDIENTE', ['Aceptada', 'Ajuste calidad', 'S/ 0.00 / L', 'S/ 1.70 / L'], ['Descuentos', 'Penalización semanal', 'no es pagable']],
    'observada con una compra' => ['OBSERVADA', -0.20, 1.50, 1, 'PENDIENTE', ['Observada', 'Penalización semanal', '− S/ 0.20 / L', 'S/ 1.50 / L', 'Descuentos', '− S/ 5.00'], ['no es pagable']],
    'rechazada no pagable' => ['RECHAZADA', 0.0, 0.0, 0, 'NO_PAGABLE', ['Rechazada', 'S/ 0.00 / L', 'La semana no es pagable por resultado de calidad rechazado.', '<dd data-hoja-estado>No pagable</dd>'], ['Penalización semanal', 'Ajuste calidad']],
    'muchas compras conservan todas las filas' => ['ACEPTADA', 0.0, 1.70, 10, 'PENDIENTE', ['Descuentos', 'Compras en planta'], ['no es pagable']],
]);

it('un comprobante pagado guardado antes de existir el resultado semanal se lee desde sus propias cifras', function () {
    $guardado = [
        'obligacionId' => 7, 'proveedorId' => 3, 'proveedorNombre' => 'Rosa Quispe', 'dni' => '01345678', 'codigo' => 'PRV-003',
        'zona' => 'Zona 1', 'semanaEtiqueta' => 'Semana 38', 'periodoInicio' => '2026-09-10', 'periodoFin' => '2026-09-16',
        'rango' => '10/09/2026 - 16/09/2026', 'litros' => 100.0, 'precioLitro' => 2.10, 'importeLeche' => 210.0,
        'penalidad' => -15.0, 'penalidadLitros' => -7.14, 'detallePenalidad' => 'Penalidad por calidad (observada)',
        'subtotalLeche' => 195.0, 'descuentos' => [], 'subtotalDescuentos' => 0.0, 'totalFinal' => 195.0,
        'estado' => 'PAGADO', 'pagadoEl' => '16/09/2026 10:00', 'anioFirma' => 2026,
    ];

    $c = ComprobantePagoProveedor::desdeArray($guardado);

    expect($c->etiquetaResultado())->toBe('Observada')
        ->and($c->ajustePorLitro())->toBe(-0.15)
        ->and($c->precioAplicadoPorLitro())->toBe(1.95)
        ->and($c->totalFinal)->toBe(195.0)
        ->and($c->etiquetaEstado())->toBe('Pagado');
});

it('una semana RECHAZADA aparece como No pagable, con S/ 0.00 y sin poder marcarse para pago', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin);
    pagos_configurar('PROVEEDOR', null, '2026-01-01', $admin);
    $proveedor = fixture_proveedor(fixture_zona('Zona 1'), '01345678');
    pagos_entrega($proveedor, 80.0, '2026-09-10', 'ACEPTADA');
    pagos_entrega($proveedor, 20.0, '2026-09-11', 'RECHAZADA');
    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-20'), $admin->id);
    $semana = ObligacionPagoModel::sole();

    $html = $this->actingAs($admin)->get(route('admin.pagos.proveedores.index'))->assertOk()->getContent();
    preg_match('/<tr data-fila .*?<\/tr>/s', $html, $fila);

    expect($fila[0])->toContain('data-estado="NO_PAGABLE"')->toContain('data-pendiente="0"')->toContain('100 L')
        ->toContain('Rechazada')->toContain('S/ 0.00')->toContain('No pagable')
        ->toMatch('/data-marcar[^>]*disabled/');

    $hoja = $this->getJson(route('admin.pagos.proveedores.preview', ['ids' => [$semana->id]]))->json('hojas.0.html');
    expect($hoja)->toContain('La semana no es pagable por resultado de calidad rechazado.')->toContain('S/ 0.00 / L');

    $this->post(route('admin.pagos.proveedores.print-and-pay'), ['ids' => [$semana->id]])->assertSessionHas('error');
    expect(PagoModel::count())->toBe(0)->and($semana->fresh()->estado)->toBe('NO_PAGABLE');
});

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
        'montos' => [$obligacion->id => '270.00'],
    ])->assertRedirect()->assertSessionHas('success');

    $pago = PagoModel::firstOrFail();

    expect($obligacion->fresh()->estado)->toBe('PAGADO')
        ->and($pago->metodo_pago)->toBe('EFECTIVO')
        ->and($pago->registrado_por_user_id)->toBe($admin->id)
        ->and($pago->pagado_at)->not->toBeNull()
        ->and($pago->impreso_at)->not->toBeNull()
        ->and($pago->lote_codigo)->toStartWith('PP-')
        ->and((float) $pago->monto)->toBe(270.0)
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
        ->assertSee('No hay liquidaciones para estos filtros');

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
