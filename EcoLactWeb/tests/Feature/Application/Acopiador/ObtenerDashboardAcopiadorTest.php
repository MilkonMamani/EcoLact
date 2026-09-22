<?php

use App\Application\Acopiador\ObtenerDashboardAcopiador;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('compone el dashboard del acopiador con las entregas y pendientes de su zona', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zonaA = fixture_zona('Zona A');
    $zonaB = fixture_zona('Zona B');
    $proveedorA = fixture_proveedor($zonaA);
    $proveedorB = fixture_proveedor($zonaB);
    $acopiadorA = fixture_acopiador($zonaA);
    $acopiadorB = fixture_acopiador($zonaB);

    $entregaA1 = fixture_entrega($proveedorA, $acopiadorA, 20.0, new DateTimeImmutable);
    fixture_analisis_confirmado($entregaA1, 'ACEPTADA', $admin->id);
    $entregaA2 = fixture_entrega($proveedorA, $acopiadorA, 15.0, new DateTimeImmutable);

    // Otra zona: no debe contar en el dashboard del acopiador A.
    fixture_entrega($proveedorB, $acopiadorB, 99.0, new DateTimeImmutable);

    $dashboard = app(ObtenerDashboardAcopiador::class)->execute(
        userId: $acopiadorA->user_id,
        zonaId: $zonaA->id,
        periodo: null,
        desde: null,
        hasta: null,
    );

    expect($dashboard->kpis['entregas'])->toBe(2);
    expect($dashboard->kpis['litros'])->toBe(35.0);
    expect($dashboard->kpis['proveedoresAtendidos'])->toBe(1);
    // fixture_analisis_confirmado inserta el análisis directamente y NO
    // transiciona el estado propio de la entrega (eso lo hace
    // FinalizarAnalisisCalidad en el flujo real): ambas entregas siguen
    // PENDIENTE_ANALISIS a nivel de columna `entregas.estado`.
    expect($dashboard->kpis['pendientesAnalisis'])->toBe(2);
    expect($dashboard->resultadosPorEntrega[$entregaA1->id])->toBe('ACEPTADA');
});
