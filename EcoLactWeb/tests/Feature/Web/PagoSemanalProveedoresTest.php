<?php

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
        ->assertSee('Pago a trabajadores')
        ->assertSee('Gestiona aquí los pagos individuales de los trabajadores');
});

it('la pantalla muestra KPIs, filtros, tabla con el cálculo y la hoja individual', function () {
    [$admin] = pago_semanal_con_pendiente();

    $this->actingAs($admin)->get(route('admin.pagos.proveedores.index'))
        ->assertOk()
        ->assertSeeInOrder(['Pago semanal a proveedores', 'Pendientes de pago', 'Litros pagables', 'Total pendiente', 'Seleccionados'])
        ->assertSeeInOrder(['Semana', 'Zona', 'Estado', 'Seleccionar pendientes de la zona'])
        ->assertSee('Se imprimirá 1 hoja por cada proveedor seleccionado.')
        ->assertSee('Proveedores de la semana')
        ->assertSeeInOrder(['Proveedor', 'Zona', 'Litros', 'Precio por', 'Penalidad /', 'Compras', 'Subtotal', 'Total final', 'Estado'])
        ->assertSee('Hoja individual del proveedor')
        ->assertSee('Imprimir y marcar pagados')
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
    expect($html)->toContain('Pago semanal a proveedor')
        ->and($html)->toContain('01345678')
        ->and($html)->toContain('PRV-'.str_pad((string) $obligacion->proveedor_id, 3, '0', STR_PAD_LEFT))
        ->and($html)->toContain('Semana 38')
        ->and($html)->toContain('10/09/2026 - 16/09/2026')
        ->and($html)->toContain('Ajuste por calidad (observada)')
        ->and($html)->toContain('- S/ 15.00')
        ->and($html)->toContain('Subtotal de leche')
        ->and($html)->toContain('S/ 300.00')
        ->and($html)->toContain('Queso Paria')
        ->and($html)->toContain('500 g')
        ->and($html)->toContain('- S/ 36.00')
        ->and($html)->toContain('Monto final total a entregar')
        ->and($html)->toContain('Pago en efectivo')
        ->and($html)->toContain('S/ 264.00')
        ->and($html)->toContain('Recibí conforme')
        ->and($html)->toContain('Entregado por (EcoLact)');
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
