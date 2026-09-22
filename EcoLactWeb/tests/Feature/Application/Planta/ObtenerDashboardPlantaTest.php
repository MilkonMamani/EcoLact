<?php

use App\Application\Planta\ObtenerDashboardPlanta;
use App\Application\Ventas\RegistrarProductoPrecio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('compone el dashboard de planta con lotes activos, leche utilizada y alertas de insumos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $lote = fixture_lote(50.0, $admin->id);
    fixture_insumo('Cuajo', 'ml', 40.0, 50.0, $admin->id);

    $producto = fixture_producto('Queso Paria');
    app(RegistrarProductoPrecio::class)->execute($producto->id, 'PROVEEDOR', 20.0, $admin->id);
    fixture_ingreso_producto($producto->id, 10.0, $admin->id, $lote->id());

    $dashboard = app(ObtenerDashboardPlanta::class)->execute(userId: $admin->id, periodo: null, desde: null, hasta: null);

    expect($dashboard->kpis['enCurso'])->toBe(1);
    expect($dashboard->kpis['litrosUtilizados'])->toBe(50.0);
    expect($dashboard->litrosPorLote[$lote->id()])->toBe(50.0);
    expect(count($dashboard->insumosBajoMinimo))->toBe(1);
    expect($dashboard->insumosBajoMinimo[0]->nombre())->toBe('Cuajo');
    expect($dashboard->productosDisponibles)->toHaveCount(1);
});
