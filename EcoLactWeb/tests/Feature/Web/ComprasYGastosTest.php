<?php

use App\Application\Caja\RegistrarMovimientoCaja;
use App\Application\Compra\RegistrarCompra;
use App\Application\Compra\RegistrarPagoCompra;
use App\Application\Produccion\GuardarPresentacionInsumo;
use App\Domain\Compra\CategoriaCompraRepository;
use App\Domain\Compra\PagoCompraRepository;
use App\Domain\Produccion\CompraInsumoRepository;
use App\Domain\Shared\Enums\MetodoPago;
use App\Infrastructure\Persistence\Models\CategoriaCompraModel;
use App\Infrastructure\Persistence\Models\CompraInsumoModel;
use App\Infrastructure\Persistence\Models\ComprobanteCompraModel;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use App\Infrastructure\Persistence\Models\MovimientoCajaModel;
use App\Infrastructure\Persistence\Models\PagoCompraModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/*
 * Inventario > Compras y gastos: un solo flujo de registro, stock que entra una sola vez al
 * recibir, Caja que solo refleja lo realmente pagado (un movimiento por pago) y trazabilidad
 * por ids guardados entre Compras, Inventario y Caja.
 */

function cg_categoria(string $codigo): int
{
    return (int) CategoriaCompraModel::where('codigo', $codigo)->value('id');
}

/**
 * @return array{cuajo: int, balde: int}
 */
function cg_cuajo(int $userId): array
{
    $cuajo = fixture_insumo('Cuajo', 'ml', 0.0, 0.0, $userId);

    return ['cuajo' => $cuajo->id(), 'balde' => app(GuardarPresentacionInsumo::class)->execute($cuajo->id(), 'Balde', 10, 'L')->id()];
}

/**
 * Cuerpo del paso de confirmación de la web.
 *
 * @param  list<array<string, mixed>>  $lineas
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function cg_cuerpo(array $lineas, array $extra = []): array
{
    return $extra + [
        'lineas' => $lineas,
        'fecha' => now()->format('Y-m-d'),
        'categoria_compra_id' => cg_categoria('INSUMOS_PRODUCCION'),
        'tipo_comprobante' => 'BOLETA',
        'numero_comprobante' => 'B001-111',
        'proveedor_comercial' => 'Distribuidora San José',
        'ruc' => '20123456786',
        'recepcion' => 'RECIBIDA',
        'condicion_pago' => 'CONTADO',
        'metodo_pago' => 'EFECTIVO',
    ];
}

/** 8 baldes de 10 L de cuajo a S/ 60 = S/ 480 (la compra C-0001 de los datos reales). */
function cg_linea_cuajo(array $e, float $cantidad = 8, float $precio = 60): array
{
    return ['tratamiento' => 'INVENTARIO', 'insumo_id' => $e['cuajo'], 'presentacion_id' => $e['balde'], 'cantidad' => $cantidad, 'precio_unitario' => $precio];
}

function cg_gaseosas(): array
{
    return ['tratamiento' => 'CONSUMO', 'descripcion' => 'Gaseosa 3 L', 'cantidad' => 4, 'unidad' => 'und', 'precio_unitario' => 12.5];
}

/** Compra registrada con el caso de uso (sin HTTP). */
function cg_registrar(int $userId, array $lineas, array $datos): CompraInsumoModel
{
    $compra = app(RegistrarCompra::class)->execute($lineas, $datos + ['categoria_compra_id' => cg_categoria('INSUMOS_PRODUCCION'), 'metodo_pago' => 'EFECTIVO'], $userId);

    return CompraInsumoModel::findOrFail($compra->id());
}

function cg_stock(int $insumoId): float
{
    return (float) InsumoModel::findOrFail($insumoId)->stock_actual;
}

// ------------------------------------------------------------ registro

it('una compra de insumos pagada al contado sube el stock una vez y genera una sola salida de caja', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);

    $this->actingAs($admin)->post(route('admin.compras.store'), cg_cuerpo([cg_linea_cuajo($e)]))->assertRedirect();

    $compra = CompraInsumoModel::sole();
    expect($compra->codigo)->toBe('C-'.str_pad((string) $compra->id, 4, '0', STR_PAD_LEFT))
        ->and($compra->origen)->toBe('MANUAL')
        ->and((float) $compra->total)->toBe(480.0)
        ->and($compra->estado_pago)->toBe('PAGADA')
        ->and($compra->estado_recepcion)->toBe('RECIBIDA')
        ->and(cg_stock($e['cuajo']))->toBe(80000.0)
        ->and(InsumoMovimientoModel::where('origen_tipo', 'COMPRA_INSUMO')->where('origen_id', $compra->id)->count())->toBe(1);

    $movimiento = MovimientoCajaModel::sole();
    $pago = PagoCompraModel::sole();
    expect([$movimiento->tipo, $movimiento->categoria, (float) $movimiento->monto, $movimiento->referencia_tipo, $movimiento->referencia_id])
        ->toBe(['SALIDA', 'INSUMOS', 480.0, 'PAGO_COMPRA', $pago->id])
        ->and($pago->movimiento_caja_id)->toBe($movimiento->id)
        ->and($compra->proveedor_comercial_id)->not->toBeNull();
});

it('una compra general sin inventario se registra y se paga sin tocar el stock ni exigir un insumo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $sal = fixture_insumo('Sal', 'g', 500.0, 0.0, $admin->id);

    $this->actingAs($admin)->post(route('admin.compras.store'), cg_cuerpo([cg_gaseosas()], [
        'categoria_compra_id' => cg_categoria('CONSUMO_INTERNO'),
        'tipo_comprobante' => 'TICKET',
    ]))->assertRedirect();

    $compra = CompraInsumoModel::sole();
    $detalle = $compra->detalles()->sole();
    expect($detalle->insumo_id)->toBeNull()
        ->and($detalle->tratamiento)->toBe('CONSUMO')
        ->and((float) $detalle->subtotal)->toBe(50.0)
        ->and($compra->estado_recepcion)->toBe('NO_APLICA')
        ->and($compra->concepto)->toBe('Gaseosa 3 L')
        ->and(InsumoMovimientoModel::where('origen_tipo', 'COMPRA_INSUMO')->count())->toBe(0)
        ->and((float) InsumoModel::find($sal->id())->stock_actual)->toBe(500.0);

    $movimiento = MovimientoCajaModel::sole();
    expect($movimiento->categoria)->toBe('GASTOS')->and((float) $movimiento->monto)->toBe(50.0);
});

it('una compra mixta solo ingresa al stock sus líneas de inventario', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);

    $compra = cg_registrar($admin->id, [cg_linea_cuajo($e, 1, 60), cg_gaseosas()], ['condicion_pago' => 'CREDITO']);

    expect($compra->detalles()->count())->toBe(2)
        ->and((float) $compra->total)->toBe(110.0)
        ->and(cg_stock($e['cuajo']))->toBe(10000.0)
        ->and(InsumoMovimientoModel::count())->toBe(1);
});

// ------------------------------------------------------------ pagos y caja

it('una compra a crédito queda pendiente y no mueve dinero de la caja', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);

    $this->actingAs($admin)->post(route('admin.compras.store'), cg_cuerpo([cg_linea_cuajo($e)], ['condicion_pago' => 'CREDITO', 'metodo_pago' => null]))->assertRedirect();

    $compra = CompraInsumoModel::sole();
    expect($compra->estado_pago)->toBe('PENDIENTE')
        ->and((float) $compra->monto_pagado)->toBe(0.0)
        ->and(MovimientoCajaModel::count())->toBe(0)
        ->and(PagoCompraModel::count())->toBe(0)
        // La compra sí recibió su stock: crédito no es lo mismo que "por recibir".
        ->and(cg_stock($e['cuajo']))->toBe(80000.0);
});

it('un pago parcial registra solo lo pagado y el saldo se paga después con otro movimiento', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);

    // S/ 1,000 = 10 baldes × S/ 100; se pagan 400 al registrar.
    $this->actingAs($admin)->post(route('admin.compras.store'), cg_cuerpo([cg_linea_cuajo($e, 10, 100)], ['condicion_pago' => 'PARCIAL', 'monto_pago' => 400]))->assertRedirect();

    $compra = CompraInsumoModel::sole();
    expect($compra->estado_pago)->toBe('PARCIAL')
        ->and((float) $compra->monto_pagado)->toBe(400.0)
        ->and(MovimientoCajaModel::pluck('monto')->map(fn ($m) => (float) $m)->all())->toBe([400.0]);

    $this->actingAs($admin)->post(route('admin.compras.pagos.store', $compra->id), ['monto' => 600, 'metodo_pago' => 'YAPE', 'numero_operacion' => 'OP-778'])
        ->assertRedirect(route('admin.compras.show', ['compra' => $compra->id, 'tab' => 'pagos']))
        ->assertSessionHas('success', 'Pago de S/ 600.00 registrado. Se registró su salida en Caja.');

    $compra->refresh();
    expect($compra->estado_pago)->toBe('PAGADA')
        ->and((float) $compra->monto_pagado)->toBe(1000.0)
        ->and(MovimientoCajaModel::orderBy('id')->pluck('monto')->map(fn ($m) => (float) $m)->all())->toBe([400.0, 600.0])
        ->and(MovimientoCajaModel::where('referencia_tipo', 'PAGO_COMPRA')->count())->toBe(2);

    // Ya pagada: no se puede volver a pagar.
    $this->actingAs($admin)->post(route('admin.compras.pagos.store', $compra->id), ['monto' => 1, 'metodo_pago' => 'EFECTIVO'])
        ->assertSessionHas('error', "La compra {$compra->codigo} ya está pagada.");
    expect(MovimientoCajaModel::count())->toBe(2);
});

it('registrar la caja de un mismo pago dos veces deja un solo movimiento', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);
    $compra = cg_registrar($admin->id, [cg_linea_cuajo($e)], ['condicion_pago' => 'CONTADO']);

    $entidad = app(CompraInsumoRepository::class)->buscarPorId($compra->id);
    $pago = app(PagoCompraRepository::class)->listarPorCompra($compra->id)[0];
    $categoria = app(CategoriaCompraRepository::class)->buscarPorCodigo('INSUMOS_PRODUCCION');
    app(RegistrarMovimientoCaja::class)->porPagoCompra($entidad, $pago, $categoria);

    expect(MovimientoCajaModel::count())->toBe(1);
});

it('rechaza pagos inválidos sin registrar nada', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);
    $compra = cg_registrar($admin->id, [cg_linea_cuajo($e)], ['condicion_pago' => 'CREDITO']);
    $pagar = fn (float $monto, MetodoPago $metodo, ?string $operacion = null) => app(RegistrarPagoCompra::class)->execute($compra->id, $monto, $metodo, $operacion, null, $admin->id);

    expect(fn () => $pagar(480.01, MetodoPago::EFECTIVO))->toThrow(DomainException::class, 'supera el saldo pendiente de S/ 480.00')
        ->and(fn () => $pagar(0, MetodoPago::EFECTIVO))->toThrow(DomainException::class, 'mayor que cero')
        ->and(fn () => $pagar(100, MetodoPago::TRANSFERENCIA))->toThrow(DomainException::class, 'número de operación');

    // Pago parcial que en realidad cubre todo (o más): debe elegirse "Pago total".
    expect(fn () => cg_registrar($admin->id, [cg_linea_cuajo($e)], ['condicion_pago' => 'PARCIAL', 'monto_pago' => 480]))->toThrow(DomainException::class, 'menor que el total');

    expect(PagoCompraModel::count())->toBe(0)->and(MovimientoCajaModel::count())->toBe(0)->and(CompraInsumoModel::count())->toBe(1);
});

it('una compra sin importe no se puede pagar ni genera caja', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);
    $compra = cg_registrar($admin->id, [['tratamiento' => 'INVENTARIO', 'insumo_id' => $e['cuajo'], 'presentacion_id' => $e['balde'], 'cantidad' => 1]], []);

    expect($compra->estado_pago)->toBe('SIN_IMPORTE')->and(MovimientoCajaModel::count())->toBe(0);
    expect(fn () => app(RegistrarPagoCompra::class)->execute($compra->id, 10, MetodoPago::EFECTIVO, null, null, $admin->id))
        ->toThrow(DomainException::class, 'no tiene importe');
});

it('lo pagado en compras coincide siempre con la caja y nunca supera el total', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);
    cg_registrar($admin->id, [cg_linea_cuajo($e)], ['condicion_pago' => 'CONTADO']);
    $parcial = cg_registrar($admin->id, [cg_linea_cuajo($e, 10, 100)], ['condicion_pago' => 'PARCIAL', 'monto_pago' => 250]);
    cg_registrar($admin->id, [cg_gaseosas()], ['categoria_compra_id' => cg_categoria('CONSUMO_INTERNO'), 'condicion_pago' => 'CREDITO']);
    app(RegistrarPagoCompra::class)->execute($parcial->id, 300, MetodoPago::EFECTIVO, null, null, $admin->id);

    $cajaCompras = (float) MovimientoCajaModel::where('referencia_tipo', 'PAGO_COMPRA')->sum('monto');
    expect($cajaCompras)->toBe(1030.0)
        ->and((float) PagoCompraModel::sum('monto'))->toBe($cajaCompras)
        ->and((float) CompraInsumoModel::sum('monto_pagado'))->toBe($cajaCompras)
        ->and(CompraInsumoModel::whereColumn('monto_pagado', '>', 'total')->count())->toBe(0)
        ->and(PagoCompraModel::whereNull('movimiento_caja_id')->count())->toBe(0);

    $this->actingAs($admin)->get(route('admin.compras.index'))
        ->assertOk()
        ->assertSeeInOrder(['Compras del periodo', '3', 'Total comprado', 'S/ 1,530.00'])
        // Lo pagado y el saldo se consultan en la ficha de cada compra (y el dinero, en Caja).
        ->assertDontSee('Importe pagado')
        ->assertDontSee('Saldo pendiente');
});

// ------------------------------------------------------------ recepción e inventario

it('una compra por recibir no sube el stock hasta confirmar la recepción, y solo una vez', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);

    $this->actingAs($admin)->post(route('admin.compras.store'), cg_cuerpo([cg_linea_cuajo($e)], ['recepcion' => 'PENDIENTE']))->assertRedirect();
    $compra = CompraInsumoModel::sole();
    expect($compra->estado_recepcion)->toBe('PENDIENTE')->and(cg_stock($e['cuajo']))->toBe(0.0)->and(InsumoMovimientoModel::count())->toBe(0);
    // El pago al contado sí salió de caja aunque la mercadería aún no llegue.
    expect(MovimientoCajaModel::count())->toBe(1);

    $this->actingAs($admin)->post(route('admin.compras.recepcion', $compra->id))
        ->assertSessionHas('success', 'Recepción confirmada. 1 artículo ingresó al inventario.');
    $this->actingAs($admin)->post(route('admin.compras.recepcion', $compra->id))
        ->assertSessionHas('error', "La compra {$compra->codigo} ya fue recibida: su stock ya ingresó.");

    expect(cg_stock($e['cuajo']))->toBe(80000.0)
        ->and(InsumoMovimientoModel::count())->toBe(1)
        ->and($compra->fresh()->estado_recepcion)->toBe('RECIBIDA')
        ->and($compra->detalles()->value('insumo_movimiento_id'))->toBe(InsumoMovimientoModel::sole()->id);
});

// ------------------------------------------------------------ comprobantes

it('los comprobantes se guardan en privado, se previsualizan, se descargan y se adjuntan después', function () {
    Storage::fake('local');
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);

    $this->actingAs($admin)->post(route('admin.compras.store'), cg_cuerpo([cg_linea_cuajo($e)], ['documentos' => [UploadedFile::fake()->image('boleta.jpg')]]))->assertRedirect();
    $compra = CompraInsumoModel::sole();

    $this->actingAs($admin)->post(route('admin.compras.comprobantes.store', $compra->id), ['tipo' => 'CONSTANCIA_TRANSFERENCIA', 'archivo' => UploadedFile::fake()->create('constancia.pdf', 120, 'application/pdf')])
        ->assertSessionHas('success', 'Documento adjuntado.');

    $documentos = ComprobanteCompraModel::orderBy('id')->get();
    expect($documentos)->toHaveCount(2)
        ->and($documentos->pluck('tipo')->all())->toBe(['BOLETA', 'CONSTANCIA_TRANSFERENCIA'])
        ->and($documentos->pluck('disco')->unique()->all())->toBe(['local']);
    Storage::disk('local')->assertExists($documentos[1]->ruta);

    $this->actingAs($admin)->get(route('admin.compras.comprobantes.show', [$compra->id, $documentos[1]->id]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($this->actingAs($admin)->get(route('admin.compras.comprobantes.show', [$compra->id, $documentos[1]->id]).'?descargar=1')->headers->get('content-disposition'))->toContain('attachment');

    $this->actingAs($admin)->get(route('admin.compras.show', ['compra' => $compra->id, 'tab' => 'comprobantes']))
        ->assertOk()->assertSee('Constancia de transferencia')->assertSee('comprobante de pago')->assertSee('Descargar');
});

it('un comprobante no se sirve desde otra compra ni a otros roles, y solo se aceptan imágenes o PDF', function () {
    Storage::fake('local');
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);
    $primera = cg_registrar($admin->id, [cg_linea_cuajo($e)], []);
    $segunda = cg_registrar($admin->id, [cg_linea_cuajo($e)], []);
    $this->actingAs($admin)->post(route('admin.compras.comprobantes.store', $primera->id), ['tipo' => 'FACTURA', 'archivo' => UploadedFile::fake()->image('factura.png')]);
    $documento = ComprobanteCompraModel::sole();

    $this->actingAs($admin)->get(route('admin.compras.comprobantes.show', [$segunda->id, $documento->id]))->assertNotFound();
    $this->actingAs(fixture_usuario('ENCARGADO_PLANTA'))->get(route('admin.compras.comprobantes.show', [$primera->id, $documento->id]))->assertForbidden();

    $this->actingAs($admin)->post(route('admin.compras.comprobantes.store', $primera->id), ['tipo' => 'OTRO', 'archivo' => UploadedFile::fake()->create('script.php', 2, 'application/x-php')])
        ->assertSessionHasErrors('archivo');
    $this->actingAs($admin)->post(route('admin.compras.comprobantes.store', $primera->id), ['tipo' => 'OTRO', 'archivo' => UploadedFile::fake()->create('enorme.pdf', 9000, 'application/pdf')])
        ->assertSessionHasErrors('archivo');
    expect(ComprobanteCompraModel::count())->toBe(1);
});

// ------------------------------------------------------------ trazabilidad

it('desde Caja se llega a la compra de origen y a su comprobante', function () {
    Storage::fake('local');
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);
    $this->actingAs($admin)->post(route('admin.compras.store'), cg_cuerpo([cg_linea_cuajo($e)], ['documentos' => [UploadedFile::fake()->image('boleta.jpg')]]));
    $compra = CompraInsumoModel::sole();
    $movimiento = MovimientoCajaModel::sole();

    $this->actingAs($admin)->get(route('admin.caja.panel', $movimiento->id))
        ->assertOk()
        ->assertSee('Ver compra de origen')
        ->assertSee(route('admin.compras.show', $compra->id))
        ->assertSee(route('admin.compras.comprobantes.show', [$compra->id, ComprobanteCompraModel::sole()->id]))
        ->assertSee('Compras y gastos')
        ->assertSee($compra->codigo);

    // Y desde la compra se vuelve al movimiento de Caja.
    $this->actingAs($admin)->get(route('admin.compras.show', $compra->id))->assertOk()->assertSee(route('admin.caja.index', ['movimiento' => $movimiento->id]));
});

it('desde Insumos cada ingreso por compra enlaza con la compra que lo generó', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);
    $compra = cg_registrar($admin->id, [cg_linea_cuajo($e)], []);

    $this->actingAs($admin)->get(route('admin.insumos.panel', $e['cuajo']))->assertOk()->assertSee(route('admin.compras.show', $compra->id));
    $this->actingAs($admin)->get(route('admin.insumos.movimientos', ['insumo_id' => $e['cuajo']]))
        ->assertOk()
        ->assertSee('Compra '.$compra->codigo)
        ->assertSee(route('admin.compras.show', $compra->id));
    // Sin acceso global «Ver movimientos»: el historial filtrado se abre desde la compra.
    $this->actingAs($admin)->get(route('admin.insumos.index'))->assertOk()->assertDontSee('Ver movimientos')->assertDontSee('Registrar compra');
    $this->actingAs($admin)->get(route('admin.compras.show', $compra->id))->assertSee(route('admin.insumos.movimientos', ['insumo_id' => $e['cuajo']]));
});

// ------------------------------------------------------------ historial

it('el historial filtra por estado de pago, tipo de compra y búsqueda', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);
    cg_registrar($admin->id, [cg_linea_cuajo($e)], ['concepto' => 'Cuajo para queso', 'condicion_pago' => 'CONTADO']);
    cg_registrar($admin->id, [cg_gaseosas()], ['concepto' => 'Refrigerio del personal', 'categoria_compra_id' => cg_categoria('CONSUMO_INTERNO'), 'condicion_pago' => 'CREDITO']);

    $cuajo = 'Cuajo · 8 baldes';
    $gaseosas = 'Gaseosa 3 L · 4 und';

    $this->actingAs($admin)->get(route('admin.compras.index', ['estado' => 'PENDIENTE']))->assertOk()->assertSee($gaseosas)->assertDontSee($cuajo);
    $this->actingAs($admin)->get(route('admin.compras.index', ['destino' => 'INVENTARIO']))->assertOk()->assertSee($cuajo)->assertDontSee($gaseosas);
    // El buscador general encuentra por concepto y también por lo que se compró.
    $this->actingAs($admin)->get(route('admin.compras.index', ['q' => 'refrigerio']))->assertOk()->assertSee($gaseosas)->assertDontSee($cuajo);
    $this->actingAs($admin)->get(route('admin.compras.index', ['q' => 'gaseosa']))->assertOk()->assertSee($gaseosas)->assertDontSee($cuajo);
    $this->actingAs($admin)->get(route('admin.compras.index', ['q' => 'cuajo']))->assertOk()->assertSee($cuajo)->assertDontSee($gaseosas);
    $this->actingAs($admin)->get(route('admin.compras.index', ['categoria' => cg_categoria('CONSUMO_INTERNO')]))->assertOk()->assertSee($gaseosas)->assertDontSee($cuajo);

    // Estado y destino quedan en «Más filtros», abierto cuando alguno está aplicado.
    $this->actingAs($admin)->get(route('admin.compras.index'))->assertOk()
        ->assertSeeInOrder(['name="q"', 'name="desde"', 'name="categoria"', 'Más filtros', 'name="estado"', 'name="destino"'], false)
        ->assertDontSee('"compras-mas-filtros" open', false);
    $this->actingAs($admin)->get(route('admin.compras.index', ['estado' => 'PENDIENTE']))->assertSee('"compras-mas-filtros" open', false);
});

it('el historial muestra qué se compró, dónde, cuánto costó y su estado; lo pagado y el saldo quedan en la ficha', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);
    // S/ 1,000 con S/ 250 pagados: saldo S/ 750.
    $compra = cg_registrar($admin->id, [cg_linea_cuajo($e, 10, 100)], ['concepto' => 'Compra de insumos', 'numero_comprobante' => 'B001-111', 'proveedor_comercial' => 'Distribuidora San José', 'condicion_pago' => 'PARCIAL', 'monto_pago' => 250]);
    $mixta = cg_registrar($admin->id, [cg_linea_cuajo($e, 1, 60), cg_gaseosas()], ['condicion_pago' => 'CREDITO']);

    $listado = $this->actingAs($admin)->get(route('admin.compras.index'))->assertOk();
    $listado->assertSeeInOrder(['Fecha', 'Compra', 'Proveedor comercial', 'Importe total', 'Estado'])
        ->assertDontSee('<th scope="col">Código</th>', false)
        ->assertDontSee('>Pagado</th>', false)
        ->assertDontSee('>Saldo pendiente</th>', false)
        // Los artículos reales, no "Compra de insumos"; la boleta como dato secundario.
        ->assertSeeInOrder(['Cuajo · 10 baldes', 'Inventario', 'B001-111', 'Distribuidora San José', 'S/ 1,000.00', 'Pago parcial'])
        ->assertDontSee('>Compra de insumos<', false)
        // Varias líneas: la primera y cuántas más.
        ->assertSee('Cuajo · 1 balde y 1 artículo más')
        ->assertSee(route('admin.compras.show', $compra->id))
        ->assertSee(route('admin.compras.show', $mixta->id));

    $this->actingAs($admin)->get(route('admin.compras.show', $compra->id))->assertOk()
        ->assertSee($compra->codigo)
        ->assertSeeInOrder(['Saldo pendiente', 'S/ 750.00']);
    expect($compra->fresh()->codigo)->not->toBeNull()->and((float) $compra->fresh()->monto_pagado)->toBe(250.0);
});

it('la ficha no muestra Trazabilidad y enlaza cada pago parcial con su movimiento de Caja', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);
    $compra = cg_registrar($admin->id, [cg_linea_cuajo($e, 10, 100)], ['condicion_pago' => 'PARCIAL', 'monto_pago' => 400]);
    app(RegistrarPagoCompra::class)->execute($compra->id, 100, MetodoPago::EFECTIVO, null, null, $admin->id);
    $cajaAntes = MovimientoCajaModel::orderBy('id')->get(['id', 'monto'])->toArray();

    $ficha = $this->actingAs($admin)->get(route('admin.compras.show', $compra->id))->assertOk();
    $ficha->assertDontSee('Trazabilidad')
        ->assertDontSee('Compra registrada')
        ->assertDontSee('Inventario actualizado')
        ->assertSeeInOrder(['Datos de la compra', 'Código', 'Fecha', 'Tipo de compra', 'Comprobante', 'Proveedor comercial', 'RUC', 'Moneda', 'Origen', 'Estado de recepción', 'Registrado por'])
        ->assertSee('Ver movimiento en Caja (S/ 400.00)')
        ->assertSee('Ver movimiento en Caja (S/ 100.00)')
        ->assertSee('Ver insumo Cuajo');
    foreach (MovimientoCajaModel::pluck('id') as $movimientoId) {
        $ficha->assertSee(route('admin.caja.index', ['movimiento' => $movimientoId]));
    }

    foreach (['resumen', 'articulos', 'comprobantes', 'pagos'] as $tab) {
        $this->actingAs($admin)->get(route('admin.compras.show', ['compra' => $compra->id, 'tab' => $tab]))->assertOk()->assertSee('id="panel-'.$tab.'"', false);
    }

    // Consultar no mueve dinero.
    expect(MovimientoCajaModel::orderBy('id')->get(['id', 'monto'])->toArray())->toBe($cajaAntes)
        ->and((float) $compra->fresh()->monto_pagado)->toBe(500.0);
});

it('un único botón «Registrar compra» abre el formulario de 3 etapas con la boleta como opción', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $html = $this->actingAs($admin)->get(route('admin.compras.index'))->assertOk();
    $html->assertSee('Registrar compra')
        ->assertDontSee('Nueva compra o gasto')
        ->assertDontSee('Escanear boleta')
        ->assertSeeInOrder(['Datos de compra', 'Artículos', 'Revisar y confirmar'])
        ->assertSee('data-paso="datos"', false)
        ->assertSee('data-paso="articulos"', false)
        ->assertSee('data-paso="revision"', false)
        ->assertDontSee('data-paso="captura"', false)
        // El OCR es opcional y vive dentro de la etapa Datos, con el mismo endpoint de siempre.
        ->assertSeeInOrder(['data-paso="datos"', 'data-foto', 'name="fecha"', 'name="proveedor_comercial"', 'name="tipo_comprobante"', 'name="numero_comprobante"', 'Más datos', 'name="categoria_compra_id"', 'name="observacion"', 'data-paso="articulos"'], false)
        ->assertSee(route('admin.compras.boleta'))
        // Ni el pago ni la recepción vienen marcados: se eligen a propósito.
        ->assertDontSee('name="condicion_pago" value="CONTADO" checked', false)
        ->assertDontSee('checked', false)
        ->assertSee('Registra: <strong class="font-medium text-text">'.$admin->username.'</strong>', false);
});

it('sin tipo de compra elegido se deduce de los artículos, sin impedir compras mixtas', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);
    $sinTipo = fn (array $lineas, array $extra = []): array => array_diff_key(cg_cuerpo($lineas, $extra), ['categoria_compra_id' => true]);
    $equipo = ['tratamiento' => 'BIEN', 'descripcion' => 'Balanza digital', 'cantidad' => 1, 'unidad' => 'und', 'precio_unitario' => 350];

    $this->actingAs($admin)->post(route('admin.compras.store'), $sinTipo([cg_linea_cuajo($e)]))->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('admin.compras.store'), $sinTipo([$equipo]))->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('admin.compras.store'), $sinTipo([cg_linea_cuajo($e, 1, 60), cg_gaseosas()]))->assertRedirect()->assertSessionHasNoErrors();

    $categorias = CompraInsumoModel::orderBy('id')->get()->map(fn ($c) => CategoriaCompraModel::find($c->categoria_compra_id)->codigo)->all();
    expect($categorias)->toBe(['INSUMOS_PRODUCCION', 'EQUIPOS_HERRAMIENTAS', 'OTROS_GASTOS'])
        ->and(CompraInsumoModel::orderByDesc('id')->first()->detalles()->count())->toBe(2)
        ->and(cg_stock($e['cuajo']))->toBe(90000.0);
});

it('la recepción se exige solo si hay artículos de inventario', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);
    $sinRecepcion = fn (array $lineas, array $extra = []): array => array_diff_key(cg_cuerpo($lineas, $extra), ['recepcion' => true]);

    $this->actingAs($admin)->post(route('admin.compras.store'), $sinRecepcion([cg_linea_cuajo($e)]))
        ->assertSessionHasErrors(['recepcion' => 'Indique si los artículos de inventario ya llegaron.']);
    expect(CompraInsumoModel::count())->toBe(0)->and(cg_stock($e['cuajo']))->toBe(0.0)->and(MovimientoCajaModel::count())->toBe(0);

    $this->actingAs($admin)->post(route('admin.compras.store'), $sinRecepcion([cg_gaseosas()], ['categoria_compra_id' => cg_categoria('CONSUMO_INTERNO')]))
        ->assertRedirect()->assertSessionHasNoErrors();
    expect(CompraInsumoModel::sole()->estado_recepcion)->toBe('NO_APLICA');

    // Tampoco se da por pagada sin elegir la condición de pago.
    $this->actingAs($admin)->post(route('admin.compras.store'), array_diff_key(cg_cuerpo([cg_gaseosas()]), ['condicion_pago' => true]))
        ->assertSessionHasErrors('condicion_pago');
    expect(CompraInsumoModel::count())->toBe(1)->and(MovimientoCajaModel::count())->toBe(1);
});

it('el insumo preseleccionado desde Insumos abre el formulario de Compras y gastos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);

    $this->actingAs($admin)->get(route('admin.compras.index', ['nueva' => 1, 'insumo' => $e['cuajo']]))
        ->assertOk()
        ->assertSee('data-abrir="manual"', false)
        ->assertSee('data-insumo="'.$e['cuajo'].'"', false);
});

// ------------------------------------------------------------ permisos, compatibilidad y duplicados

it('solo el administrador accede a Compras y gastos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);
    $compra = cg_registrar($admin->id, [cg_linea_cuajo($e)], ['condicion_pago' => 'CREDITO']);

    foreach (['ENCARGADO_PLANTA', 'VENTAS'] as $rol) {
        $usuario = fixture_usuario($rol);
        $this->actingAs($usuario)->get(route('admin.compras.index'))->assertForbidden();
        $this->actingAs($usuario)->get(route('admin.compras.show', $compra->id))->assertForbidden();
        $this->actingAs($usuario)->post(route('admin.compras.store'), cg_cuerpo([cg_linea_cuajo($e)]))->assertForbidden();
        $this->actingAs($usuario)->post(route('admin.compras.pagos.store', $compra->id), ['monto' => 10, 'metodo_pago' => 'EFECTIVO'])->assertForbidden();
        $this->actingAs($usuario)->post(route('admin.compras.recepcion', $compra->id))->assertForbidden();
    }

    expect(CompraInsumoModel::count())->toBe(1)->and(PagoCompraModel::count())->toBe(0);
    $this->actingAs($admin)->get(route('admin.compras.index'))->assertOk()->assertSee('Compras y gastos');
});

it('las rutas anteriores de compras de insumos redirigen a la ficha nueva', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);
    $compra = cg_registrar($admin->id, [cg_linea_cuajo($e)], []);

    $this->actingAs($admin)->get('/admin/compras-insumo/'.$compra->id)->assertRedirect(route('admin.compras.show', $compra->id))->assertStatus(301);
    $this->actingAs($admin)->get(route('admin.compras.show', $compra->id))->assertOk()->assertSee($compra->codigo);
});

it('reenviar el mismo formulario no duplica la compra ni el pago', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);
    $cuerpo = cg_cuerpo([cg_linea_cuajo($e)], ['condicion_pago' => 'CREDITO', 'clave_envio' => 'clave-compra-1']);

    $this->actingAs($admin)->post(route('admin.compras.store'), $cuerpo)->assertRedirect();
    $compra = CompraInsumoModel::sole();
    $this->actingAs($admin)->post(route('admin.compras.store'), $cuerpo)
        ->assertRedirect(route('admin.compras.show', $compra->id))
        ->assertSessionHas('error', 'Esta operación ya se había registrado: no se volvió a registrar.');

    $pago = ['monto' => 100, 'metodo_pago' => 'EFECTIVO', 'clave_envio' => 'clave-pago-1'];
    $this->actingAs($admin)->post(route('admin.compras.pagos.store', $compra->id), $pago);
    $this->actingAs($admin)->post(route('admin.compras.pagos.store', $compra->id), $pago);

    expect(CompraInsumoModel::count())->toBe(1)
        ->and(cg_stock($e['cuajo']))->toBe(80000.0)
        ->and(PagoCompraModel::count())->toBe(1)
        ->and(MovimientoCajaModel::count())->toBe(1);
});

it('la migración de compras históricas enlaza la salida de caja existente sin duplicar stock ni dinero', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $e = cg_cuajo($admin->id);
    $ahora = now()->format('Y-m-d H:i:s');

    // Una compra como la registraba el sistema anterior: compra + detalle + movimiento de stock + salida COMPRA_INSUMOS.
    $compraId = DB::table('compras_insumo')->insertGetId(['origen' => 'MANUAL', 'proveedor_comercial' => 'Distribuidora San José', 'ruc' => '20123456786', 'numero_comprobante' => 'B001-111', 'fecha' => now()->format('Y-m-d'), 'total' => 480, 'registrado_por_user_id' => $admin->id, 'created_at' => $ahora, 'updated_at' => $ahora]);
    $movimientoId = DB::table('insumo_movimientos')->insertGetId(['insumo_id' => $e['cuajo'], 'tipo' => 'ENTRADA', 'motivo' => 'INGRESO_COMPRA', 'cantidad' => 80000, 'origen_tipo' => 'COMPRA_INSUMO', 'origen_id' => $compraId, 'fecha' => $ahora, 'registrado_por_user_id' => $admin->id, 'created_at' => $ahora, 'updated_at' => $ahora]);
    DB::table('insumos')->where('id', $e['cuajo'])->update(['stock_actual' => 80000]);
    DB::table('compra_insumo_detalles')->insert(['compra_insumo_id' => $compraId, 'insumo_id' => $e['cuajo'], 'presentacion_insumo_id' => $e['balde'], 'presentacion_nombre' => 'Balde', 'presentacion_cantidad' => 10, 'presentacion_unidad' => 'L', 'cantidad_presentaciones' => 8, 'precio_unitario' => 60, 'cantidad_base' => 80000, 'unidad_base' => 'ml', 'insumo_movimiento_id' => $movimientoId, 'created_at' => $ahora, 'updated_at' => $ahora]);
    $cajaId = DB::table('movimientos_caja')->insertGetId(['tipo' => 'SALIDA', 'categoria' => 'INSUMOS', 'monto' => 480, 'concepto' => 'Compra de insumos', 'descripcion' => 'Boleta B001-111', 'referencia' => 'C-0001', 'referencia_tipo' => 'COMPRA_INSUMOS', 'referencia_id' => $compraId, 'registrado_por_user_id' => $admin->id, 'fecha' => $ahora, 'created_at' => $ahora, 'updated_at' => $ahora]);

    $migracion = require database_path('migrations/2026_10_29_100006_backfill_compras_historicas_a_compras_y_gastos.php');
    $migracion->up();
    $migracion->up();

    $compra = CompraInsumoModel::findOrFail($compraId);
    expect($compra->codigo)->toBe('C-'.str_pad((string) $compraId, 4, '0', STR_PAD_LEFT))
        ->and($compra->estado_pago)->toBe('PAGADA')
        ->and((float) $compra->monto_pagado)->toBe(480.0)
        ->and($compra->tipo_comprobante)->toBe('BOLETA')
        ->and($compra->categoria_compra_id)->toBe(cg_categoria('INSUMOS_PRODUCCION'))
        ->and(PagoCompraModel::sole()->movimiento_caja_id)->toBe($cajaId)
        ->and(MovimientoCajaModel::count())->toBe(1)
        ->and(InsumoMovimientoModel::count())->toBe(1)
        ->and(cg_stock($e['cuajo']))->toBe(80000.0)
        ->and($compra->detalles()->value('descripcion'))->toBe('Cuajo')
        ->and((float) $compra->detalles()->value('subtotal'))->toBe(480.0);

    // Ya pagada: el sistema nuevo no permite volver a pagar los S/ 480.
    expect(fn () => app(RegistrarPagoCompra::class)->execute($compraId, 480, MetodoPago::EFECTIVO, null, null, $admin->id))->toThrow(DomainException::class, 'ya está pagada');
    $this->actingAs($admin)->get(route('admin.caja.panel', $cajaId))->assertOk()->assertSee(route('admin.compras.show', $compraId));
});
