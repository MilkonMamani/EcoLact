<?php

use App\Application\Liquidacion\GenerarLiquidacion;
use App\Application\Liquidacion\RegistrarPago;
use App\Application\Pago\GenerarPagosPendientes;
use App\Application\Pago\RegistrarPagoObligacion;
use App\Domain\Shared\Enums\MetodoPago;
use App\Infrastructure\Persistence\Models\LiquidacionModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\PagoModel;
use App\Infrastructure\Persistence\Models\PagoProveedorModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function ejecutar_backfill(): void
{
    (require database_path('migrations/2026_09_23_100004_backfill_obligaciones_desde_liquidaciones.php'))->up();
}

function liquidar(User $admin, string $nombres, string $desde, string $hasta): LiquidacionModel
{
    $proveedor = fixture_proveedor(fixture_zona());
    $proveedor->update(['nombres' => $nombres, 'apellidos' => 'Legado']);
    pagos_entrega($proveedor, 100.0, $desde, 'ACEPTADA');

    $liquidacion = app(GenerarLiquidacion::class)->execute($proveedor->id, new DateTimeImmutable($desde), new DateTimeImmutable($hasta), $admin->id);

    return LiquidacionModel::findOrFail($liquidacion->id());
}

it('migra las liquidaciones pendientes y pagadas a obligaciones sin alterar las tablas anteriores', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin);

    $pendiente = liquidar($admin, 'Pendiente', '2026-02-05', '2026-02-11');
    $pagada = liquidar($admin, 'Pagada', '2026-02-05', '2026-02-11');
    app(RegistrarPago::class)->execute($pagada->id, (float) $pagada->total_pagar, $admin->id, new DateTimeImmutable('2026-02-13'), 'Sobre entregado');

    $liquidacionesAntes = LiquidacionModel::count();
    $pagosProveedorAntes = PagoProveedorModel::count();

    ejecutar_backfill();

    $obligacionPendiente = ObligacionPagoModel::where('liquidacion_id', $pendiente->id)->firstOrFail();
    $obligacionPagada = ObligacionPagoModel::where('liquidacion_id', $pagada->id)->firstOrFail();

    expect($obligacionPendiente->estado)->toBe('PENDIENTE')
        ->and((float) $obligacionPendiente->monto_calculado)->toBe(210.0)
        ->and($obligacionPendiente->configuracion_pago_id)->toBeNull()
        ->and($obligacionPendiente->beneficiario_nombre)->toBe('Pendiente Legado')
        ->and($obligacionPendiente->periodo_inicio->format('Y-m-d'))->toBe('2026-02-05')
        ->and($obligacionPendiente->periodo_fin->format('Y-m-d'))->toBe('2026-02-11')
        ->and($obligacionPagada->estado)->toBe('PAGADO');

    $pago = PagoModel::where('obligacion_pago_id', $obligacionPagada->id)->firstOrFail();

    expect((float) $pago->monto)->toBe(210.0)
        ->and($pago->metodo_pago)->toBe('EFECTIVO')
        ->and($pago->fecha_pago->format('Y-m-d'))->toBe('2026-02-13')
        ->and($pago->observacion)->toBe('Sobre entregado')
        ->and($pago->registrado_por_user_id)->toBe($admin->id)
        ->and($pago->pago_proveedor_id)->not->toBeNull()
        ->and(PagoModel::where('obligacion_pago_id', $obligacionPendiente->id)->exists())->toBeFalse()
        ->and(LiquidacionModel::count())->toBe($liquidacionesAntes)
        ->and(PagoProveedorModel::count())->toBe($pagosProveedorAntes);
});

it('la migración de respaldo se puede repetir sin duplicar obligaciones', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin);
    liquidar($admin, 'Unica', '2026-02-05', '2026-02-11');

    ejecutar_backfill();
    ejecutar_backfill();

    expect(ObligacionPagoModel::count())->toBe(1);
});

it('no migra las liquidaciones anuladas', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin);
    $liquidacion = liquidar($admin, 'Anulada', '2026-02-05', '2026-02-11');
    $liquidacion->update(['estado' => 'ANULADA']);

    ejecutar_backfill();

    expect(ObligacionPagoModel::count())->toBe(0);
});

it('un pago parcial previo migra solo el saldo pendiente', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin);
    $liquidacion = liquidar($admin, 'Parcial', '2026-02-05', '2026-02-11');
    app(RegistrarPago::class)->execute($liquidacion->id, 10.0, $admin->id);

    ejecutar_backfill();

    $obligacion = ObligacionPagoModel::firstOrFail();

    expect($obligacion->estado)->toBe('PENDIENTE')
        ->and((float) $obligacion->monto_calculado)->toBe(200.0)
        ->and($obligacion->base_calculo['abonos_previos'])->toEqual(10);
});

it('la generación no vuelve a pagar las entregas ya liquidadas por el flujo anterior', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin);
    pagos_configurar('PROVEEDOR', null, '2026-01-01', $admin);
    $liquidada = liquidar($admin, 'Legado', '2026-02-05', '2026-02-11');
    ejecutar_backfill();

    $resultado = app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-03-01'));

    expect($resultado->creadas)->toBe(0)
        ->and(ObligacionPagoModel::count())->toBe(1)
        ->and(LiquidacionModel::count())->toBe(1)
        ->and($liquidada->id)->toBe(ObligacionPagoModel::firstOrFail()->liquidacion_id);
});

it('una entrega nueva del proveedor de una liquidación anterior genera su propio pendiente sin tocar el pagado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin, 1.70);
    pagos_configurar('PROVEEDOR', null, '2026-09-21', $admin, precio: 1.70);
    $proveedor = fixture_proveedor(fixture_zona());
    pagos_entrega($proveedor, 30.0, '2026-09-14', 'ACEPTADA');
    $liquidacion = app(GenerarLiquidacion::class)->execute($proveedor->id, new DateTimeImmutable('2026-09-14'), new DateTimeImmutable('2026-09-15'), $admin->id);
    app(RegistrarPago::class)->execute($liquidacion->id(), 51.0, $admin->id);
    ejecutar_backfill();
    pagos_entrega($proveedor, 20.0, '2026-09-15');

    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-21'));
    app(GenerarPagosPendientes::class)->execute(new DateTimeImmutable('2026-09-21'));

    $pagos = ObligacionPagoModel::orderBy('id')->get();

    expect($pagos)->toHaveCount(2)
        ->and($pagos[0]->estado)->toBe('PAGADO')
        ->and((float) $pagos[0]->monto_calculado)->toBe(51.0)
        ->and($pagos[1]->estado)->toBe('PENDIENTE')
        ->and((float) $pagos[1]->monto_calculado)->toBe(34.0)
        ->and(ObligacionPagoModel::where('estado', 'PENDIENTE')->sum('monto_calculado'))->toEqual(34);
});

it('pagar una obligación migrada de una liquidación anterior deja su liquidación PAGADA', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin);
    $liquidacion = liquidar($admin, 'Legado', '2026-02-05', '2026-02-11');
    ejecutar_backfill();
    $obligacion = ObligacionPagoModel::where('liquidacion_id', $liquidacion->id)->firstOrFail();

    app(RegistrarPagoObligacion::class)->execute($obligacion->id, MetodoPago::YAPE, 'Y-1', new DateTimeImmutable('2026-02-13'), null, null, $admin->id, null, new DateTimeImmutable('2026-02-14'));

    expect($obligacion->fresh()->estado)->toBe('PAGADO')
        ->and($liquidacion->fresh()->estado)->toBe('PAGADA')
        ->and(PagoProveedorModel::where('liquidacion_id', $liquidacion->id)->count())->toBe(1);
});
