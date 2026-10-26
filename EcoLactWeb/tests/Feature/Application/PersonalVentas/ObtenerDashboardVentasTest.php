<?php

use App\Application\PersonalVentas\ObtenerDashboardVentas;
use App\Application\Ventas\RegistrarProductoPrecio;
use App\Application\Ventas\RegistrarVentaProducto;
use App\Infrastructure\Persistence\Models\ProductoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('compone el dashboard de ventas con kpis, stock crítico y más vendidos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $ventas = fixture_usuario('VENTAS');

    $productoA = ProductoModel::create(['nombre' => 'Queso Paria', 'unidad_medida' => 'UNIDAD', 'estado' => 'ACTIVO']);
    $productoB = ProductoModel::create(['nombre' => 'Yogur', 'unidad_medida' => 'LITRO', 'estado' => 'ACTIVO']);

    app(RegistrarProductoPrecio::class)->execute($productoA->id, 'PUBLICO', 20.0, $admin->id);
    fixture_ingreso_producto($productoA->id, 5.0, $admin->id);
    app(RegistrarProductoPrecio::class)->execute($productoB->id, 'PUBLICO', 8.0, $admin->id);
    fixture_ingreso_producto($productoB->id, 10.0, $admin->id);

    app(RegistrarVentaProducto::class)->execute(
        productoId: $productoA->id,
        cantidad: 5.0,
        formaPago: 'CONTADO',
        registradoPorUserId: $ventas->id,
    );

    $dashboard = app(ObtenerDashboardVentas::class)->execute(userId: $ventas->id, periodo: null, desde: null, hasta: null);

    expect($dashboard->kpis['ventas'])->toBe(1);
    expect($dashboard->kpis['totalVendido'])->toBe(100.0);
    expect($dashboard->kpis['ticketPromedio'])->toBe(100.0);
    expect($dashboard->stockCritico)->toHaveCount(1);
    expect($dashboard->stockCritico[0]['producto'])->toBe('Queso Paria');
    expect($dashboard->masVendidos)->toHaveCount(1);
    expect($dashboard->masVendidos[0]['producto'])->toBe('Queso Paria');
});

it('sin ventas en el periodo, el ticket promedio queda en null', function () {
    $ventas = fixture_usuario('VENTAS');

    $dashboard = app(ObtenerDashboardVentas::class)->execute(userId: $ventas->id, periodo: 'hoy', desde: null, hasta: null);

    expect($dashboard->kpis['ventas'])->toBe(0);
    expect($dashboard->kpis['ticketPromedio'])->toBeNull();
});
