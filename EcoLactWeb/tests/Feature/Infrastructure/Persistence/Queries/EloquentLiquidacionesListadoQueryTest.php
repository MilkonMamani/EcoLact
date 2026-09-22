<?php

use App\Application\Liquidacion\GenerarLiquidacion;
use App\Application\Liquidacion\LiquidacionesFiltro;
use App\Application\Liquidacion\ValorizarEntrega;
use App\Infrastructure\Persistence\Models\LiquidacionModel;
use App\Infrastructure\Persistence\Queries\EloquentLiquidacionesListadoQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../../Support/EcoLactFixtures.php';

uses(RefreshDatabase::class, TestCase::class);

function elq_filtroVacio(): LiquidacionesFiltro
{
    return LiquidacionesFiltro::resolver(null, null, null, null);
}

it('calcula los KPIs y arma las filas con proveedor, periodo e incidencia', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zonaNorte = fixture_zona('Norte');
    $zonaSur = fixture_zona('Sur');
    $proveedorNorte = fixture_proveedor($zonaNorte, '11112222');
    $proveedorSur = fixture_proveedor($zonaSur, '33334444');
    $acopiadorNorte = fixture_acopiador($zonaNorte);
    $acopiadorSur = fixture_acopiador($zonaSur);

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));
    fixture_regla_pago('OBSERVADA', 'MONTO', -0.20, new DateTimeImmutable('2026-01-01'));

    $entregaA = fixture_entrega($proveedorNorte, $acopiadorNorte, 30.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entregaA, 'ACEPTADA', $admin->id);
    app(ValorizarEntrega::class)->execute($entregaA->id);
    $liquidacionA = app(GenerarLiquidacion::class)->execute($proveedorNorte->id, new DateTimeImmutable('2026-02-05'), new DateTimeImmutable('2026-02-11'), $admin->id);

    $entregaB = fixture_entrega($proveedorSur, $acopiadorSur, 20.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entregaB, 'OBSERVADA', $admin->id);
    app(ValorizarEntrega::class)->execute($entregaB->id);
    app(GenerarLiquidacion::class)->execute($proveedorSur->id, new DateTimeImmutable('2026-02-05'), new DateTimeImmutable('2026-02-11'), $admin->id);

    $datos = (new EloquentLiquidacionesListadoQuery)->obtener(elq_filtroVacio());

    expect($datos->kpis['proveedoresLiquidados'])->toBe(2);
    expect($datos->kpis['litrosEntregados'])->toBe(50.0);
    expect($datos->kpis['pendientesPago'])->toBe(2);
    expect($datos->filas)->toHaveCount(2);

    $filaA = collect($datos->filas)->firstWhere('id', $liquidacionA->id());
    expect($filaA['proveedorDni'])->toBe('11112222');
    expect($filaA['tuvoIncidencia'])->toBeFalse();
});

it('filtra por estado, incidencia, zona y búsqueda', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zonaNorte = fixture_zona('Norte');
    $zonaSur = fixture_zona('Sur');
    $proveedorNorte = fixture_proveedor($zonaNorte, '55556666');
    $proveedorSur = fixture_proveedor($zonaSur, '77778888');
    $acopiadorNorte = fixture_acopiador($zonaNorte);
    $acopiadorSur = fixture_acopiador($zonaSur);

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));
    fixture_regla_pago('OBSERVADA', 'MONTO', -0.20, new DateTimeImmutable('2026-01-01'));

    $entregaA = fixture_entrega($proveedorNorte, $acopiadorNorte, 30.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entregaA, 'ACEPTADA', $admin->id);
    app(ValorizarEntrega::class)->execute($entregaA->id);
    app(GenerarLiquidacion::class)->execute($proveedorNorte->id, new DateTimeImmutable('2026-02-05'), new DateTimeImmutable('2026-02-11'), $admin->id);

    $entregaB = fixture_entrega($proveedorSur, $acopiadorSur, 20.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entregaB, 'OBSERVADA', $admin->id);
    app(ValorizarEntrega::class)->execute($entregaB->id);
    app(GenerarLiquidacion::class)->execute($proveedorSur->id, new DateTimeImmutable('2026-02-05'), new DateTimeImmutable('2026-02-11'), $admin->id);

    $porIncidencia = LiquidacionesFiltro::resolver(null, null, 'con_incidencia', null);
    expect((new EloquentLiquidacionesListadoQuery)->obtener($porIncidencia)->filas)->toHaveCount(1);

    $porZona = LiquidacionesFiltro::resolver(null, null, null, $zonaNorte->id);
    expect((new EloquentLiquidacionesListadoQuery)->obtener($porZona)->filas)->toHaveCount(1);

    $porBusqueda = LiquidacionesFiltro::resolver('55556666', null, null, null);
    expect((new EloquentLiquidacionesListadoQuery)->obtener($porBusqueda)->filas)->toHaveCount(1);

    $porEstado = LiquidacionesFiltro::resolver(null, 'PENDIENTE', null, null);
    expect((new EloquentLiquidacionesListadoQuery)->obtener($porEstado)->filas)->toHaveCount(2);
});

it('filtra por periodo y expone la lista de periodos con liquidaciones', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    fixture_precio_leche(1.70, new DateTimeImmutable('2026-01-01'), $admin->id);
    fixture_regla_pago('ACEPTADA', 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'));

    $entregaA = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-02-05'));
    fixture_analisis_confirmado($entregaA, 'ACEPTADA', $admin->id);
    app(ValorizarEntrega::class)->execute($entregaA->id);
    $liquidacionA = app(GenerarLiquidacion::class)->execute($proveedor->id, new DateTimeImmutable('2026-02-05'), new DateTimeImmutable('2026-02-11'), $admin->id);

    $entregaB = fixture_entrega($proveedor, $acopiador, 20.0, new DateTimeImmutable('2026-02-12'));
    fixture_analisis_confirmado($entregaB, 'ACEPTADA', $admin->id);
    app(ValorizarEntrega::class)->execute($entregaB->id);
    $liquidacionB = app(GenerarLiquidacion::class)->execute($proveedor->id, new DateTimeImmutable('2026-02-12'), new DateTimeImmutable('2026-02-18'), $admin->id);

    $datos = (new EloquentLiquidacionesListadoQuery)->obtener(elq_filtroVacio());
    expect($datos->periodos)->toHaveCount(2);

    $periodoA = LiquidacionModel::find($liquidacionA->id())->periodo_id;

    $porPeriodo = LiquidacionesFiltro::resolver(null, null, null, null, $periodoA);
    $filtrado = (new EloquentLiquidacionesListadoQuery)->obtener($porPeriodo)->filas;

    expect($filtrado)->toHaveCount(1);
    expect($filtrado[0]['id'])->toBe($liquidacionA->id());
    expect($filtrado[0]['id'])->not->toBe($liquidacionB->id());
});
