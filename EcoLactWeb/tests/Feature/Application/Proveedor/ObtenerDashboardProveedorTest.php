<?php

use App\Application\Comunicacion\CrearComunicado;
use App\Application\Proveedor\ObtenerDashboardProveedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('compone entregas recientes, último análisis y comunicados del proveedor autenticado', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    $entrega = fixture_entrega($proveedor, $acopiador, 25.0);
    fixture_analisis_confirmado($entrega, 'ACEPTADA', $admin->id);

    app(CrearComunicado::class)->execute(
        titulo: 'Aviso para el proveedor',
        contenido: 'Contenido',
        tipo: 'AVISO',
        alcance: 'INDIVIDUAL',
        creadoPorUserId: $admin->id,
        destinatarioUserIds: [$proveedor->user_id],
    );

    $dashboard = app(ObtenerDashboardProveedor::class)->execute(
        userId: $proveedor->user_id,
        proveedorId: $proveedor->id,
        periodo: null,
        desde: null,
        hasta: null,
    );

    expect($dashboard->kpis['entregas'])->toBe(1);
    expect($dashboard->kpis['litros'])->toBe(25.0);
    expect($dashboard->ultimoAnalisis?->resultado()?->value)->toBe('ACEPTADA');
    expect($dashboard->ultimaLiquidacion)->toBeNull();
    expect($dashboard->ultimasEntregas)->toHaveCount(1);
    expect($dashboard->comunicadosRecientes)->toHaveCount(1);
});

it('acota las entregas al periodo seleccionado sin afectar el resto del dashboard', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    fixture_entrega($proveedor, $acopiador, 10.0, new DateTimeImmutable('2020-01-01'));
    fixture_entrega($proveedor, $acopiador, 15.0);

    $dashboard = app(ObtenerDashboardProveedor::class)->execute(
        userId: $proveedor->user_id,
        proveedorId: $proveedor->id,
        periodo: 'esta_semana',
        desde: null,
        hasta: null,
    );

    expect($dashboard->kpis['entregas'])->toBe(1);
    expect($dashboard->kpis['litros'])->toBe(15.0);
});
