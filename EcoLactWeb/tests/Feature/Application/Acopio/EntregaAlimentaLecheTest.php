<?php

use App\Application\Acopio\ActualizarEntrega;
use App\Application\Acopio\AnularEntrega;
use App\Application\Acopio\RegistrarEntrega;
use App\Application\Calidad\RegistrarEntregaMuestreada;
use App\Application\Pago\RegistrarPagoObligacion;
use App\Application\Produccion\IniciarProduccion;
use App\Domain\Shared\Enums\MetodoPago;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function registrarEntregaDePrueba(float $litros, ?string $token = null): array
{
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    $entrega = app(RegistrarEntrega::class)->execute(
        metodoIdentificacion: 'DNI',
        identificador: $proveedor->dni,
        acopiadorUserId: $acopiador->user_id,
        litros: $litros,
        tokenIdempotencia: $token,
    );

    return [$entrega, $proveedor, $acopiador];
}

function stockDeLeche(): float
{
    return (float) InsumoModel::where('codigo', 'LECHE')->value('stock_actual');
}

it('20 L entregados aumentan 20 L el stock de Leche con un movimiento INGRESO_ACOPIO trazado a la entrega', function () {
    expect(stockDeLeche())->toBe(0.0);

    [$entrega] = registrarEntregaDePrueba(20.0);

    expect(stockDeLeche())->toBe(20.0);

    $movimiento = InsumoMovimientoModel::where('origen_tipo', 'ENTREGA')->where('origen_id', $entrega->id())->firstOrFail();
    expect($movimiento->motivo)->toBe('INGRESO_ACOPIO');
    expect($movimiento->tipo)->toBe('ENTRADA');
    expect((float) $movimiento->cantidad)->toBe(20.0);
    expect($movimiento->insumo_id)->toBe(fixture_leche()->id);
});

it('las entregas se acumulan en el stock de Leche', function () {
    registrarEntregaDePrueba(20.0);
    registrarEntregaDePrueba(35.5);

    expect(stockDeLeche())->toBe(55.5);
});

it('un reintento con el mismo token de idempotencia no duplica el ingreso de leche', function () {
    registrarEntregaDePrueba(20.0, 'token-abc');
    registrarEntregaDePrueba(20.0, 'token-abc');

    expect(stockDeLeche())->toBe(20.0);
    expect(InsumoMovimientoModel::where('origen_tipo', 'ENTREGA')->count())->toBe(1);
});

it('si el ingreso de leche falla la entrega tampoco se guarda (misma transacción)', function () {
    InsumoModel::where('codigo', 'LECHE')->update(['codigo' => null]);

    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    expect(fn () => app(RegistrarEntrega::class)->execute('DNI', $proveedor->dni, $acopiador->user_id, 20.0))
        ->toThrow(DomainException::class, 'Leche no está configurado');

    expect(EntregaModel::count())->toBe(0);
});

it('anular una entrega registra un REVERSO y conserva el ingreso original', function () {
    [$entrega] = registrarEntregaDePrueba(20.0);
    $admin = fixture_usuario('ADMINISTRADOR');

    $anulada = app(AnularEntrega::class)->execute($entrega->id(), 'Registrada por error', $admin->id);

    expect($anulada->estado()->value)->toBe('ANULADA');
    expect(stockDeLeche())->toBe(0.0);

    $movimientos = InsumoMovimientoModel::where('origen_tipo', 'ENTREGA')->where('origen_id', $entrega->id())->orderBy('id')->get();
    expect($movimientos->pluck('motivo')->all())->toBe(['INGRESO_ACOPIO', 'REVERSO']);
    expect($movimientos->pluck('tipo')->all())->toBe(['ENTRADA', 'SALIDA']);
    expect((float) $movimientos[1]->cantidad)->toBe(20.0);
});

it('no se puede anular dos veces ni sin motivo', function () {
    [$entrega] = registrarEntregaDePrueba(20.0);
    $admin = fixture_usuario('ADMINISTRADOR');

    expect(fn () => app(AnularEntrega::class)->execute($entrega->id(), '  ', $admin->id))->toThrow(DomainException::class, 'motivo');

    app(AnularEntrega::class)->execute($entrega->id(), 'Error', $admin->id);

    expect(fn () => app(AnularEntrega::class)->execute($entrega->id(), 'Otra vez', $admin->id))->toThrow(DomainException::class, 'ya está anulada');
    expect(stockDeLeche())->toBe(0.0);
});

it('no se puede anular una entrega cuya leche ya se usó en producción (el stock nunca queda negativo)', function () {
    [$entrega] = registrarEntregaDePrueba(20.0);
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = fixture_producto('Queso');
    fixture_receta($producto->id, [['insumo_id' => fixture_leche()->id, 'cantidad' => 10.0, 'unidad' => 'L']], $admin->id);
    app(IniciarProduccion::class)->execute([['producto_id' => $producto->id, 'cantidad' => 1.5]], $admin->id);

    expect(stockDeLeche())->toBe(5.0);

    expect(fn () => app(AnularEntrega::class)->execute($entrega->id(), 'Error', $admin->id))
        ->toThrow(DomainException::class, 'ya no están disponibles');

    expect(stockDeLeche())->toBe(5.0);
    expect(EntregaModel::find($entrega->id())->estado)->toBe('PENDIENTE_ANALISIS');
});

it('no se puede anular una entrega que ya forma parte de un pago realizado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    pagos_configurar('PROVEEDOR', null, '2026-01-01', $admin);

    $entrega = app(RegistrarEntrega::class)->execute('DNI', $proveedor->dni, $acopiador->user_id, 20.0);
    $obligacion = ObligacionPagoModel::firstOrFail();
    app(RegistrarPagoObligacion::class)->execute($obligacion->id, MetodoPago::EFECTIVO, null, new DateTimeImmutable, null, null, $admin->id);

    expect(fn () => app(AnularEntrega::class)->execute($entrega->id(), 'Error', $admin->id))
        ->toThrow(DomainException::class, 'pago realizado');

    expect(stockDeLeche())->toBe(20.0);
});

it('anular una entrega con pago pendiente lo recalcula sin ella', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    pagos_configurar('PROVEEDOR', null, '2026-01-01', $admin);

    $primera = app(RegistrarEntrega::class)->execute('DNI', $proveedor->dni, $acopiador->user_id, 20.0);
    $segunda = app(RegistrarEntrega::class)->execute('DNI', $proveedor->dni, $acopiador->user_id, 10.0);

    expect((float) ObligacionPagoModel::firstOrFail()->monto_calculado)->toBe(51.0);

    app(AnularEntrega::class)->execute($segunda->id(), 'Error de digitación', $admin->id);
    expect((float) ObligacionPagoModel::firstOrFail()->monto_calculado)->toBe(34.0);

    app(AnularEntrega::class)->execute($primera->id(), 'Error de digitación', $admin->id);
    expect(ObligacionPagoModel::count())->toBe(0);
});

it('corregir los litros de una entrega registra solo la diferencia en el inventario', function () {
    [$entrega] = registrarEntregaDePrueba(20.0);

    app(ActualizarEntrega::class)->execute($entrega->id(), 'Se pesó mal', litros: 25.0);
    expect(stockDeLeche())->toBe(25.0);

    app(ActualizarEntrega::class)->execute($entrega->id(), 'Se pesó mal', litros: 18.0);
    expect(stockDeLeche())->toBe(18.0);

    $motivos = InsumoMovimientoModel::where('origen_id', $entrega->id())->orderBy('id')->pluck('motivo')->all();
    expect($motivos)->toBe(['INGRESO_ACOPIO', 'INGRESO_ACOPIO', 'REVERSO']);
});

it('editar una entrega no borra el enlace con su parada de recorrido ni su token', function () {
    [$entrega] = registrarEntregaDePrueba(20.0, 'token-parada');
    EntregaModel::where('id', $entrega->id())->update(['observacion' => null]);

    app(ActualizarEntrega::class)->execute($entrega->id(), 'Corrección', observacion: 'Nota');

    expect(EntregaModel::find($entrega->id())->token_idempotencia)->toBe('token-parada');
});

it('una muestra tomada por el analista (rechazada, nunca acopiada) no ingresa leche al inventario', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $analista = fixture_usuario('ANALISTA_CALIDAD');

    app(RegistrarEntregaMuestreada::class)->execute(
        metodoIdentificacion: 'DNI',
        identificador: $proveedor->dni,
        analistaUserId: $analista->id,
        litros: 15.0,
    );

    expect(EntregaModel::count())->toBe(1);
    expect(stockDeLeche())->toBe(0.0);
});
