<?php

use App\Application\Pago\ObtenerComprobantesPagoProveedor;
use App\Application\Pago\RegistrarPagoObligacion;
use App\Application\Pago\SincronizarPagosProveedor;
use App\Domain\Pago\PeriodoPago;
use App\Domain\Shared\Enums\MetodoPago;
use App\Infrastructure\Persistence\Models\AuditoriaModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoDetalleModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\PagoModel;
use App\Infrastructure\Persistence\Models\PeriodoLiquidacionModel;
use App\Infrastructure\Persistence\Models\PrecioLecheModel;
use App\Infrastructure\Persistence\Models\ProductoModel;
use App\Infrastructure\Persistence\Models\VentaProductoModel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

require_once __DIR__.'/../Support/PagosFixtures.php';
uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-09-27 10:00:00');
});

function semana_unica_escenario(): array
{
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_configurar('PROVEEDOR', null, '2026-01-01', $admin, precio: 1.70, ajustes: [
        'ACEPTADA' => ['tipo_ajuste' => 'MONTO', 'valor_ajuste' => 0.25],
        'OBSERVADA' => ['tipo_ajuste' => 'MONTO', 'valor_ajuste' => -0.20],
    ]);

    return [$admin, fixture_proveedor(fixture_zona())];
}

it('acumula una o cinco entregas en una sola obligación y es idempotente', function (int $cantidad, float $litros, float $monto) {
    [, $proveedor] = semana_unica_escenario();
    foreach (array_slice(['2026-09-17', '2026-09-18', '2026-09-20', '2026-09-21', '2026-09-22'], 0, $cantidad) as $fecha) {
        pagos_entrega($proveedor, 20.0, $fecha);
    }
    app(SincronizarPagosProveedor::class)->execute();
    $id = ObligacionPagoModel::firstOrFail()->id;
    app(SincronizarPagosProveedor::class)->execute();

    $o = ObligacionPagoModel::sole();
    expect($o->id)->toBe($id)->and((float) $o->base_calculo['litros_pagables'])->toBe($litros)
        ->and((float) $o->monto_calculado)->toBe($monto)
        ->and($o->detalles)->toHaveCount($cantidad)
        ->and($o->fecha_pago_programada->format('Y-m-d'))->toBe('2026-09-23');
})->with(['una entrega' => [1, 20.0, 34.0], 'cinco entregas' => [5, 100.0, 170.0]]);

it('consolida calidad compras y comprobante sin pagar anuladas ni rechazadas', function () {
    [$admin, $proveedor] = semana_unica_escenario();
    pagos_entrega($proveedor, 20, '2026-09-17', 'ACEPTADA');
    pagos_entrega($proveedor, 30, '2026-09-18');
    pagos_entrega($proveedor, 25, '2026-09-20');
    pagos_entrega($proveedor, 15, '2026-09-21');
    pagos_entrega($proveedor, 10, '2026-09-22', 'OBSERVADA');
    pagos_entrega($proveedor, 50, '2026-09-19', 'RECHAZADA');
    pagos_entrega($proveedor, 50, '2026-09-19')->update(['estado' => 'ANULADA']);
    $periodo = PeriodoLiquidacionModel::create(['fecha_inicio' => '2026-09-17', 'fecha_fin' => '2026-09-23', 'estado' => 'ABIERTO']);
    $producto = ProductoModel::create(['nombre' => 'Queso', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    foreach ([15, 8] as $importe) {
        VentaProductoModel::create([
            'producto_id' => $producto->id, 'proveedor_id' => $proveedor->id, 'tipo_cliente' => 'PROVEEDOR',
            'cantidad' => 1, 'precio_unitario' => $importe, 'importe_total' => $importe,
            'forma_pago' => 'DESCUENTO_PLANILLA', 'periodo_id' => $periodo->id, 'estado' => 'REGISTRADA',
            'fecha' => '2026-09-21', 'registrado_por_user_id' => $admin->id,
        ]);
    }

    app(SincronizarPagosProveedor::class)->execute();

    $o = ObligacionPagoModel::sole();
    $c = app(ObtenerComprobantesPagoProveedor::class)->execute([$o->id])[0];
    expect($c->litros)->toBe(100.0)->and($c->importeLeche)->toBe(170.0)
        ->and($c->penalidad)->toBe(3.0)->and($c->subtotalLeche)->toBe(173.0)
        ->and($c->subtotalDescuentos)->toBe(23.0)->and($c->totalFinal)->toBe(150.0)
        ->and($c->descuentos)->toHaveCount(2);
});

it('respeta el precio de cada entrega cuando cambia dentro de la semana', function () {
    [$admin, $proveedor] = semana_unica_escenario();
    PrecioLecheModel::query()->update(['fecha_fin' => '2026-09-18']);
    fixture_precio_leche(2.0, new DateTimeImmutable('2026-09-19'), $admin->id);
    pagos_entrega($proveedor, 20, '2026-09-17');
    pagos_entrega($proveedor, 30, '2026-09-21');

    app(SincronizarPagosProveedor::class)->execute();

    $o = ObligacionPagoModel::sole();
    $c = app(ObtenerComprobantesPagoProveedor::class)->execute([$o->id])[0];
    expect($c->litros)->toBe(50.0)->and($c->importeLeche)->toBe(94.0)->and($c->totalFinal)->toBe(94.0)
        ->and($o->detalles->pluck('precio_base')->map(fn ($precio): float => (float) $precio)->all())->toBe([1.7, 2.0]);
});

it('separa proveedores y semanas sin repetir la identidad semanal', function () {
    [, $proveedor] = semana_unica_escenario();
    $otro = fixture_proveedor(fixture_zona());
    pagos_entrega($proveedor, 20, '2026-09-17');
    pagos_entrega($proveedor, 30, '2026-09-24');
    pagos_entrega($otro, 25, '2026-09-17');

    app(SincronizarPagosProveedor::class)->execute();
    app(SincronizarPagosProveedor::class)->execute();

    expect(ObligacionPagoModel::count())->toBe(3)
        ->and(ObligacionPagoModel::where('proveedor_id', $proveedor->id)->count())->toBe(2)
        ->and(ObligacionPagoModel::where('proveedor_id', $otro->id)->count())->toBe(1);
});

it('centraliza jueves a miércoles y el pago del miércoles incluso entre años', function (string $fecha, string $inicio, string $fin) {
    $periodo = PeriodoPago::semanaQueContiene(new DateTimeImmutable($fecha));
    expect($periodo->clave())->toBe($inicio)->and($periodo->fin->format('Y-m-d'))->toBe($fin)
        ->and($periodo->fechaPagoProgramada()->format('Y-m-d'))->toBe($fin)
        ->and($periodo->fechaPagoProgramada()->format('N'))->toBe('3');
})->with([
    ['2026-09-17', '2026-09-17', '2026-09-23'], ['2026-09-18', '2026-09-17', '2026-09-23'],
    ['2026-09-19', '2026-09-17', '2026-09-23'], ['2026-09-20', '2026-09-17', '2026-09-23'],
    ['2026-09-21', '2026-09-17', '2026-09-23'], ['2026-09-22', '2026-09-17', '2026-09-23'],
    ['2026-09-23', '2026-09-17', '2026-09-23'], ['2027-01-01', '2026-12-31', '2027-01-06'],
]);

it('la base de datos rechaza otro inicio parcial de la misma semana aun con otra clave', function (string $estado) {
    [$admin, $proveedor] = semana_unica_escenario();
    pagos_entrega($proveedor, 20, '2026-09-17');
    app(SincronizarPagosProveedor::class)->execute();
    $o = ObligacionPagoModel::sole();
    $o->update(['estado' => $estado]);

    expect(fn () => DB::table('obligaciones_pago')->insert([
        'tipo_beneficiario' => 'PROVEEDOR', 'proveedor_id' => $proveedor->id,
        'beneficiario_nombre' => 'Duplicado', 'periodo_inicio' => '2026-09-21', 'periodo_fin' => '2026-09-22',
        'periodicidad' => 'SEMANAL', 'concepto' => 'Duplicado', 'monto_calculado' => 10,
        'estado' => 'PENDIENTE', 'clave_unicidad' => 'OTRA-CLAVE',
    ]))->toThrow(UniqueConstraintViolationException::class);
})->with(['PENDIENTE', 'PAGADO']);

it('rechaza el pago antes del miércoles sin registrar dinero', function () {
    [$admin, $proveedor] = semana_unica_escenario();
    pagos_entrega($proveedor, 20, '2026-09-17');
    app(SincronizarPagosProveedor::class)->execute();
    $o = ObligacionPagoModel::sole();

    expect(fn () => app(RegistrarPagoObligacion::class)->execute(
        $o->id, MetodoPago::EFECTIVO, null, new DateTimeImmutable('2026-09-22'), null, null, $admin->id,
        hoy: new DateTimeImmutable('2026-09-22'),
    ))->toThrow(DomainException::class, 'miércoles 23/09/2026');
    expect(PagoModel::count())->toBe(0)->and($o->fresh()->estado)->toBe('PENDIENTE');
});

it('paga el miércoles y envía entregas tardías a la siguiente semana sin tocar el histórico', function () {
    [$admin, $proveedor] = semana_unica_escenario();
    pagos_entrega($proveedor, 30, '2026-09-17');
    app(SincronizarPagosProveedor::class)->execute();
    $o = ObligacionPagoModel::sole();
    $this->travelTo('2026-09-23 12:00:00');
    $snapshot = app(ObtenerComprobantesPagoProveedor::class)->execute([$o->id])[0]->toArray();
    app(RegistrarPagoObligacion::class)->execute($o->id, MetodoPago::EFECTIVO, null, new DateTimeImmutable('2026-09-23'), null, null, $admin->id, comprobanteSnapshot: $snapshot);
    $pagoAntes = PagoModel::sole()->toArray();
    pagos_entrega($proveedor, 20, '2026-09-22');
    pagos_entrega($proveedor, 10, '2026-09-24');

    app(SincronizarPagosProveedor::class)->execute();
    app(SincronizarPagosProveedor::class)->execute();

    $pendiente = ObligacionPagoModel::where('estado', 'PENDIENTE')->sole();
    expect(ObligacionPagoModel::count())->toBe(2)->and($o->fresh()->monto_calculado)->toBe('51.00')
        ->and(PagoModel::sole()->toArray())->toBe($pagoAntes)
        ->and($pendiente->periodo_inicio->format('Y-m-d'))->toBe('2026-09-24')
        ->and($pendiente->monto_calculado)->toBe('51.00');
});

it('fusiona pendientes verificables conservando una auditoría y todas las entregas', function () {
    [, $proveedor] = semana_unica_escenario();
    pagos_entrega($proveedor, 30, '2026-09-17');
    app(SincronizarPagosProveedor::class)->execute();
    $primera = ObligacionPagoModel::sole();
    (require database_path('migrations/2026_10_02_100001_enforce_unique_provider_payment_week.php'))->down();
    $entrega = pagos_entrega($proveedor, 20, '2026-09-21');
    $otra = $primera->replicate(['semana_proveedor'])->forceFill([
        'periodo_inicio' => '2026-09-21', 'clave_unicidad' => 'LEGADO', 'clave_abierta' => null, 'monto_calculado' => 34,
    ]);
    $otra->save();
    ObligacionPagoDetalleModel::create([
        'obligacion_pago_id' => $otra->id, 'entrega_id' => $entrega->id,
        'litros_entregados' => 20, 'litros_pagables' => 20, 'precio_base' => 1.7,
        'ajuste_calidad' => 0, 'precio_final' => 1.7, 'subtotal' => 34,
    ]);

    app(SincronizarPagosProveedor::class)->execute();
    (require database_path('migrations/2026_10_02_100001_enforce_unique_provider_payment_week.php'))->up();

    $o = ObligacionPagoModel::sole();
    expect($o->id)->toBe($primera->id)->and($o->monto_calculado)->toBe('85.00')->and($o->detalles)->toHaveCount(2)
        ->and(AuditoriaModel::where('accion', 'CONSOLIDAR_SEMANA')->where('registro_id', $otra->id)->exists())->toBeTrue();
});
