<?php

use App\Infrastructure\Persistence\Models\LiquidacionModel;
use App\Infrastructure\Persistence\Models\PagoProveedorModel;
use App\Infrastructure\Persistence\Models\PeriodoLiquidacionModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function crearLiquidacionParaPolicy(ProveedorModel $proveedor, int $generadoPorUserId): LiquidacionModel
{
    $periodo = PeriodoLiquidacionModel::create([
        'fecha_inicio' => '2026-02-05',
        'fecha_fin' => '2026-02-11',
        'estado' => 'ABIERTO',
    ]);

    return LiquidacionModel::create([
        'periodo_id' => $periodo->id,
        'proveedor_id' => $proveedor->id,
        'total_litros_entregados' => 100.0,
        'total_litros_pagables' => 100.0,
        'subtotal' => 170.0,
        'total_ajustes' => 0.0,
        'total_pagar' => 170.0,
        'estado' => 'PENDIENTE',
        'fecha_calculo' => now(),
        'generado_por_user_id' => $generadoPorUserId,
        'precio_litro_aplicado' => 1.70,
        'tuvo_incidencia' => false,
    ]);
}

it('un PROVEEDOR puede ver su propia liquidación', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $admin = fixture_usuario('ADMINISTRADOR');
    $liquidacion = crearLiquidacionParaPolicy($proveedor, $admin->id);

    expect($proveedor->user->can('view', $liquidacion))->toBeTrue();
});

it('un PROVEEDOR NO puede ver la liquidación de otro proveedor', function () {
    $zona = fixture_zona();
    $proveedorA = fixture_proveedor($zona);
    $proveedorB = fixture_proveedor($zona);
    $admin = fixture_usuario('ADMINISTRADOR');
    $liquidacion = crearLiquidacionParaPolicy($proveedorA, $admin->id);

    expect($proveedorB->user->can('view', $liquidacion))->toBeFalse();
});

it('un ACOPIADOR no administra ni consulta liquidaciones', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');
    $liquidacion = crearLiquidacionParaPolicy($proveedor, $admin->id);

    expect($acopiador->user->can('view', $liquidacion))->toBeFalse();
});

it('un PROVEEDOR puede ver su propio pago', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $admin = fixture_usuario('ADMINISTRADOR');
    $liquidacion = crearLiquidacionParaPolicy($proveedor, $admin->id);

    $pago = PagoProveedorModel::create([
        'liquidacion_id' => $liquidacion->id,
        'fecha_pago' => now(),
        'monto' => 170.0,
        'estado' => 'REGISTRADO',
        'registrado_por_user_id' => $admin->id,
    ]);

    expect($proveedor->user->can('view', $pago))->toBeTrue();
});

it('un PROVEEDOR NO puede ver el pago de otro proveedor', function () {
    $zona = fixture_zona();
    $proveedorA = fixture_proveedor($zona);
    $proveedorB = fixture_proveedor($zona);
    $admin = fixture_usuario('ADMINISTRADOR');
    $liquidacion = crearLiquidacionParaPolicy($proveedorA, $admin->id);

    $pago = PagoProveedorModel::create([
        'liquidacion_id' => $liquidacion->id,
        'fecha_pago' => now(),
        'monto' => 170.0,
        'estado' => 'REGISTRADO',
        'registrado_por_user_id' => $admin->id,
    ]);

    expect($proveedorB->user->can('view', $pago))->toBeFalse();
});
