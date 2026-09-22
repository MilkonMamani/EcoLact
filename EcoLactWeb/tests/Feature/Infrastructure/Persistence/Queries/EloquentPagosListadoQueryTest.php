<?php

use App\Application\Liquidacion\GenerarLiquidacion;
use App\Application\Liquidacion\PagosFiltro;
use App\Application\Liquidacion\RegistrarPago;
use App\Application\Liquidacion\ValorizarEntrega;
use App\Infrastructure\Persistence\Queries\EloquentPagosListadoQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(RefreshDatabase::class, TestCase::class);

function epgq_filtroVacio(): PagosFiltro
{
    return PagosFiltro::resolver(null, null, null);
}

it('calcula los KPIs y arma las filas como obligación de pago por liquidación', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona('Norte');
    $proveedorPendiente = fixture_proveedor($zona, '11112222');
    $proveedorPagado = fixture_proveedor($zona, '33334444');
    $acopiador = fixture_acopiador($zona);

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));

    $entregaA = fixture_entrega($proveedorPendiente, $acopiador, 30.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entregaA, 'ACEPTADA', $admin->id);
    app(ValorizarEntrega::class)->execute($entregaA->id);
    $liquidacionPendiente = app(GenerarLiquidacion::class)->execute($proveedorPendiente->id, new DateTimeImmutable('2026-02-05'), new DateTimeImmutable('2026-02-11'), $admin->id);

    $entregaB = fixture_entrega($proveedorPagado, $acopiador, 20.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entregaB, 'ACEPTADA', $admin->id);
    app(ValorizarEntrega::class)->execute($entregaB->id);
    $liquidacionPagada = app(GenerarLiquidacion::class)->execute($proveedorPagado->id, new DateTimeImmutable('2026-02-05'), new DateTimeImmutable('2026-02-11'), $admin->id);
    app(RegistrarPago::class)->execute($liquidacionPagada->id(), $liquidacionPagada->totalPagar(), $admin->id);

    $datos = (new EloquentPagosListadoQuery)->obtener(epgq_filtroVacio());

    expect($datos->kpis['pendientesPago'])->toBe(1);
    expect($datos->kpis['pagosRealizados'])->toBe(1);
    expect($datos->kpis['montoPendiente'])->toBe(round($liquidacionPendiente->totalPagar(), 2));
    expect($datos->kpis['montoPagado'])->toBe(round($liquidacionPagada->totalPagar(), 2));
    expect($datos->filas)->toHaveCount(2);

    $filaPagada = collect($datos->filas)->firstWhere('proveedorDni', '33334444');
    expect($filaPagada['estadoPago'])->toBe('PAGADA');
    expect($filaPagada['fechaPago'])->not->toBeNull();

    $filaPendiente = collect($datos->filas)->firstWhere('proveedorDni', '11112222');
    expect($filaPendiente['estadoPago'])->toBe('PENDIENTE');
    expect($filaPendiente['fechaPago'])->toBeNull();
});

it('filtra por estado y búsqueda', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona, '55556666');
    $acopiador = fixture_acopiador($zona);
    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));

    $entrega = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $admin->id);
    app(ValorizarEntrega::class)->execute($entrega->id);
    app(GenerarLiquidacion::class)->execute($proveedor->id, new DateTimeImmutable('2026-02-05'), new DateTimeImmutable('2026-02-11'), $admin->id);

    $porEstado = PagosFiltro::resolver(null, 'PENDIENTE', null);
    expect((new EloquentPagosListadoQuery)->obtener($porEstado)->filas)->toHaveCount(1);

    $porBusqueda = PagosFiltro::resolver('55556666', null, null);
    expect((new EloquentPagosListadoQuery)->obtener($porBusqueda)->filas)->toHaveCount(1);
});
