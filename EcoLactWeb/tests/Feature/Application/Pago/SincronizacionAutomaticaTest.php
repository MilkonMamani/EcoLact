<?php

use App\Application\Acopio\RegistrarEntrega;
use App\Application\Calidad\FinalizarAnalisisCalidad;
use App\Application\Calidad\RegistrarAnalisisCalidad;
use App\Application\Pago\Contracts\PagosPendientesProveedorStore;
use App\Application\Pago\ObligacionesPagoFiltro;
use App\Application\Pago\ObtenerObligacionesPago;
use App\Application\Pago\RegistrarPagoObligacion;
use App\Application\Pago\SincronizarPagosProveedor;
use App\Domain\Pago\PeriodoPago;
use App\Domain\Shared\Enums\EstadoObligacionPago;
use App\Domain\Shared\Enums\MetodoPago;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\ObligacionPagoModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/**
 * Escenario base: precio S/ 1.70, ajustes de calidad activados (OBSERVADA -0.30) y una
 * configuración de proveedor vigente. Devuelve [admin, proveedor, acopiador].
 */
function escenario_de_acopio(): array
{
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin, 1.70);
    pagos_configurar('PROVEEDOR', null, '2026-09-01', $admin, precio: 1.70);
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $proveedor->update(['nombres' => 'Milkon', 'apellidos' => 'Mamani']);
    $acopiador = fixture_acopiador($zona);

    return [$admin, $proveedor, $acopiador];
}

function registrar_entrega_de(ProveedorModel $proveedor, User $acopiador, float $litros, string $fechaHora)
{
    return app(RegistrarEntrega::class)->execute(
        metodoIdentificacion: 'DNI',
        identificador: $proveedor->dni,
        acopiadorUserId: $acopiador->id,
        litros: $litros,
        fechaHora: new DateTimeImmutable($fechaHora),
    );
}

function pendiente_total(): float
{
    return (float) app(ObtenerObligacionesPago::class)->execute(ObligacionesPagoFiltro::resolver(), new DateTimeImmutable('2026-09-20'))['kpis']['totalPendiente'];
}

it('registrar una entrega sin análisis genera solo su pago pendiente, sin pasar por Actualizar pendientes', function () {
    [, $proveedor, $acopiador] = escenario_de_acopio();

    registrar_entrega_de($proveedor, $acopiador->user, 20.0, '2026-09-15 14:34:30');

    $obligacion = ObligacionPagoModel::firstOrFail();

    expect((float) InsumoModel::where('codigo', 'LECHE')->value('stock_actual'))->toBe(20.0)
        ->and($obligacion->estado)->toBe('PENDIENTE')
        ->and((float) $obligacion->monto_calculado)->toBe(34.0)
        ->and($obligacion->beneficiario_nombre)->toBe('Milkon Mamani')
        ->and(AnalisisCalidadModel::count())->toBe(0);
});

it('acopiar no se bloquea si todavía no hay configuración de pago: la entrega y el stock se registran', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    registrar_entrega_de($proveedor, $acopiador->user, 20.0, '2026-09-15 10:00');

    expect(EntregaModel::count())->toBe(1)
        ->and((float) InsumoModel::where('codigo', 'LECHE')->value('stock_actual'))->toBe(20.0)
        ->and(ObligacionPagoModel::count())->toBe(0)
        ->and($admin->id)->toBeInt();
});

it('si falla la sincronización del pago, la entrega y el stock tampoco se guardan (una sola transacción)', function () {
    [, $proveedor, $acopiador] = escenario_de_acopio();
    $this->app->bind(PagosPendientesProveedorStore::class, fn () => new class implements PagosPendientesProveedorStore
    {
        public function semanasCerradas(int $proveedorId): array
        {
            return [];
        }

        public function liberarPendientes(int $proveedorId): array
        {
            return [];
        }

        public function guardarPendiente(?int $existenteId, int $proveedorId, string $nombre, ?int $configuracionId, PeriodoPago $periodo, float $monto, array $baseCalculo, array $detalles, ?int $actorUserId, EstadoObligacionPago $estado = EstadoObligacionPago::PENDIENTE): bool
        {
            throw new RuntimeException('Fallo simulado');
        }

        public function eliminarPendientes(array $ids): void {}

        public function descuentosDisponibles(int $proveedorId, PeriodoPago $periodo, array $semanasSinPago = []): array
        {
            return [];
        }
    });

    expect(fn () => registrar_entrega_de($proveedor, $acopiador->user, 20.0, '2026-09-15 10:00'))->toThrow(RuntimeException::class);

    expect(EntregaModel::count())->toBe(0)
        ->and((float) InsumoModel::where('codigo', 'LECHE')->value('stock_actual'))->toBe(0.0);
});

it('confirmar el análisis recalcula el pago pendiente con el ajuste de calidad', function () {
    [$admin, $proveedor, $acopiador] = escenario_de_acopio();
    fixture_regla_calidad(fixture_parametro('GRASA')->id, 3.0, 6.0, 'OBSERVADA');
    $entrega = registrar_entrega_de($proveedor, $acopiador->user, 100.0, '2026-09-15 10:00');
    expect((float) ObligacionPagoModel::firstOrFail()->monto_calculado)->toBe(170.0);

    // GRASA 2.0 queda fuera del rango 3-6: OBSERVADA (1.70 - 0.30 = 1.40).
    $analisis = app(RegistrarAnalisisCalidad::class)->execute(entregaId: $entrega->id(), valores: ['GRASA' => 2.0]);
    expect((float) ObligacionPagoModel::firstOrFail()->monto_calculado)->toBe(170.0);

    app(FinalizarAnalisisCalidad::class)->execute($analisis->id(), $admin->id);

    expect((float) ObligacionPagoModel::firstOrFail()->monto_calculado)->toBe(140.0)
        ->and(ObligacionPagoModel::count())->toBe(1);
});

it('confirmar un análisis no se bloquea aunque no exista precio de leche', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    fixture_regla_calidad(fixture_parametro('GRASA')->id, 3.0, 6.0, 'OBSERVADA');
    $entrega = registrar_entrega_de($proveedor, $acopiador->user, 30.0, '2026-09-15 10:00');
    $analisis = app(RegistrarAnalisisCalidad::class)->execute(entregaId: $entrega->id(), valores: ['GRASA' => 4.0]);

    $confirmado = app(FinalizarAnalisisCalidad::class)->execute($analisis->id(), $admin->id);

    expect($confirmado->estaConfirmado())->toBeTrue()->and(ObligacionPagoModel::count())->toBe(0);
});

it('escenario completo: pago histórico intacto y saldo pendiente correcto con entregas posteriores', function () {
    [$admin, $proveedor, $acopiador] = escenario_de_acopio();

    // Entrega 1: 30 L x 1.70 = S/ 51 pendiente -> se paga.
    registrar_entrega_de($proveedor, $acopiador->user, 30.0, '2026-09-14 22:58');
    $primero = ObligacionPagoModel::firstOrFail();
    expect((float) $primero->monto_calculado)->toBe(51.0)
        ->and(pendiente_total())->toBe(51.0);

    app(RegistrarPagoObligacion::class)->execute($primero->id, MetodoPago::EFECTIVO, null, new DateTimeImmutable('2026-09-16'), null, null, $admin->id, null, new DateTimeImmutable('2026-09-20'));
    expect(pendiente_total())->toBe(0.0);

    // Entrega 2: 20 L de la misma semana -> otro pendiente de S/ 34; el pagado no cambia.
    registrar_entrega_de($proveedor, $acopiador->user, 20.0, '2026-09-15 14:34');
    expect(pendiente_total())->toBe(34.0)
        ->and((float) $primero->fresh()->monto_calculado)->toBe(51.0)
        ->and($primero->fresh()->estado)->toBe('PAGADO');

    // Entrega 3: otros 20 L -> el pendiente abierto suma S/ 68 en total.
    registrar_entrega_de($proveedor, $acopiador->user, 20.0, '2026-09-16 09:00');
    $datos = app(ObtenerObligacionesPago::class)->execute(ObligacionesPagoFiltro::resolver(), new DateTimeImmutable('2026-09-20'));

    expect(ObligacionPagoModel::count())->toBe(2)
        ->and(ObligacionPagoModel::where('estado', 'PENDIENTE')->count())->toBe(1)
        ->and((float) ObligacionPagoModel::where('estado', 'PENDIENTE')->value('monto_calculado'))->toBe(68.0)
        ->and($datos['kpis']['pendientes'])->toBe(1)
        ->and($datos['kpis']['totalPendiente'])->toBe(68.0)
        ->and(ObligacionPagoModel::where('estado', 'PAGADO')->sum('monto_calculado'))->toEqual(51);
});

it('los KPIs de Pagos salen de las obligaciones reales: pendientes por proveedor y total pendiente', function () {
    [$admin, $proveedor, $acopiador] = escenario_de_acopio();
    $otro = fixture_proveedor($proveedor->zona);
    $otro->update(['nombres' => 'Luis', 'apellidos' => 'Calcina Mamani']);
    registrar_entrega_de($proveedor, $acopiador->user, 20.0, '2026-09-15 10:00');
    registrar_entrega_de($otro, $acopiador->user, 20.0, '2026-09-17 10:00');
    registrar_entrega_de($proveedor, $acopiador->user, 30.0, '2026-09-14 10:00');
    $pagado = ObligacionPagoModel::where('proveedor_id', $proveedor->id)->firstOrFail();
    app(RegistrarPagoObligacion::class)->execute($pagado->id, MetodoPago::EFECTIVO, null, new DateTimeImmutable('2026-09-16'), null, null, $admin->id, null, new DateTimeImmutable('2026-09-20'));

    $this->actingAs($admin)->get(route('admin.pagos.index'))
        ->assertOk()
        ->assertSee('S/ 34.00')
        ->assertSee('S/ 85.00');

    $datos = app(ObtenerObligacionesPago::class)->execute(ObligacionesPagoFiltro::resolver(), new DateTimeImmutable('2026-09-20'));

    // Milkon: 30+20 L en la misma semana (pagado 50 L = 85) y Luis 20 L pendientes (34).
    expect($datos['kpis']['pendientes'])->toBe(1)
        ->and($datos['kpis']['totalPendiente'])->toBe(34.0)
        ->and($datos['kpis']['totalPagado'])->toBe(85.0);
});

it('ejecutar la sincronización dos veces no duplica pagos', function () {
    [, $proveedor, $acopiador] = escenario_de_acopio();
    registrar_entrega_de($proveedor, $acopiador->user, 20.0, '2026-09-15 10:00');

    app(SincronizarPagosProveedor::class)->execute();
    app(SincronizarPagosProveedor::class)->execute();

    expect(ObligacionPagoModel::count())->toBe(1);
});
