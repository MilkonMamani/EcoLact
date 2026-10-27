<?php

use App\Application\Calidad\ObtenerDashboardCalidad;
use App\Application\Calidad\SeleccionarMuestraCalidad;
use App\Application\Comunicacion\CrearComunicado;
use App\Domain\Shared\Enums\MetodoSeleccionMuestreo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('compone muestras pendientes (no todas las entregas), KPIs del periodo y comunicados recientes', function () {
    $analista = fixture_usuario('CALIDAD');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    $entregaMuestreada = fixture_entrega($proveedor, $acopiador, 30.0);
    $entregaNoMuestreada = fixture_entrega($proveedor, $acopiador, 25.0);
    app(SeleccionarMuestraCalidad::class)->execute($entregaMuestreada->id, $analista->id, MetodoSeleccionMuestreo::MANUAL);
    $entregaAnalizada = fixture_entrega($proveedor, $acopiador, 20.0);
    fixture_analisis_confirmado($entregaAnalizada, 'ACEPTADA', $analista->id);
    $entregaAnalizada->update(['estado' => 'ANALIZADA']);

    app(CrearComunicado::class)->execute(
        titulo: 'Aviso para calidad',
        contenido: 'Contenido',
        tipo: 'AVISO',
        alcance: 'INDIVIDUAL',
        creadoPorUserId: $analista->id,
        destinatarioUserIds: [$analista->id],
    );

    $dashboard = app(ObtenerDashboardCalidad::class)->execute(
        userId: $analista->id,
        periodo: null,
        desde: null,
        hasta: null,
    );

    expect($dashboard->muestrasPendientes)->toBe(1);
    expect($dashboard->kpisPeriodo['analizados'])->toBe(1);
    expect($dashboard->kpisPeriodo['aceptados'])->toBe(1);
    expect(collect($dashboard->muestrasFilas)->pluck('entregaId'))->toContain($entregaMuestreada->id)
        ->not->toContain($entregaNoMuestreada->id);
    expect($dashboard->comunicadosRecientes)->toHaveCount(1);
});
