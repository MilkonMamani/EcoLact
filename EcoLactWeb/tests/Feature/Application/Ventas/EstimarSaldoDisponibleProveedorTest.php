<?php

use App\Application\Ventas\EstimarSaldoDisponibleProveedor;
use App\Domain\Pago\PeriodoLiquidacionRepository;
use App\Infrastructure\Persistence\Models\PeriodoLiquidacionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/**
 * Saldo que el proveedor puede usar para compras "a cuenta": misma regla semanal
 * de calidad que el pago (precio 2.10, OBSERVADA -0.30 por litro).
 *
 * @param  list<array{float, string|null}>  $entregas  [litros, resultado]
 */
function saldo_semana(array $entregas): float
{
    $admin = fixture_usuario('ADMINISTRADOR');
    pagos_precios_y_reglas($admin);
    $proveedor = fixture_proveedor(fixture_zona());

    foreach ($entregas as $indice => [$litros, $resultado]) {
        pagos_entrega($proveedor, $litros, '2026-09-1'.($indice + 1), $resultado);
    }

    $periodo = PeriodoLiquidacionModel::create(['fecha_inicio' => '2026-09-10', 'fecha_fin' => '2026-09-16', 'estado' => 'ABIERTO']);

    return app(EstimarSaldoDisponibleProveedor::class)->execute($proveedor->id, app(PeriodoLiquidacionRepository::class)->buscarPorId($periodo->id));
}

it('estima el saldo con el peor resultado de la semana', function (array $entregas, float $saldo) {
    expect(saldo_semana($entregas))->toBe($saldo);
})->with([
    'aceptadas: precio normal' => [[[50.0, 'ACEPTADA'], [50.0, 'ACEPTADA']], 210.0],
    'una observada: descuento a todos los litros' => [[[50.0, 'ACEPTADA'], [50.0, 'OBSERVADA']], 180.0],
    'una rechazada: semana sin pago' => [[[50.0, 'ACEPTADA'], [50.0, 'RECHAZADA']], 0.0],
    'sin análisis: precio normal' => [[[100.0, null]], 210.0],
]);
