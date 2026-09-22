<?php

use App\Application\Acopio\RegistrarEntrega;
use App\Application\Produccion\IniciarProduccion;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\EntregaModel;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/**
 * La calidad es un MONITOREO del proveedor, no un paso obligatorio de cada
 * entrega: no bloquea que la leche entre al inventario ni que se produzca.
 */
it('un proveedor puede entregar leche sin ningún análisis y esa leche alimenta el stock', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    app(RegistrarEntrega::class)->execute('DNI', $proveedor->dni, $acopiador->user_id, 40.0);

    expect(AnalisisCalidadModel::count())->toBe(0);
    expect((float) InsumoModel::where('codigo', 'LECHE')->value('stock_actual'))->toBe(40.0);
});

it('se puede producir con leche de entregas que nunca fueron analizadas', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    app(RegistrarEntrega::class)->execute('DNI', $proveedor->dni, $acopiador->user_id, 40.0);

    $queso = fixture_producto('Queso');
    fixture_receta($queso->id, [['insumo_id' => fixture_leche()->id, 'cantidad' => 10.0, 'unidad' => 'L']], $admin->id);

    $lote = app(IniciarProduccion::class)->execute([['producto_id' => $queso->id, 'cantidad' => 4]], $admin->id);

    expect($lote->estado())->toBe('EN_PROCESO');
    expect((float) InsumoModel::where('codigo', 'LECHE')->value('stock_actual'))->toBe(0.0);
});

it('un análisis realizado después no quita leche del inventario, ni siquiera si el resultado es RECHAZADA', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = app(RegistrarEntrega::class)->execute('DNI', $proveedor->dni, $acopiador->user_id, 40.0);

    fixture_analisis_confirmado(EntregaModel::find($entrega->id()), 'RECHAZADA', $admin->id);

    expect((float) InsumoModel::where('codigo', 'LECHE')->value('stock_actual'))->toBe(40.0);
});

it('los análisis de calidad siguen funcionando de forma independiente de la producción', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);

    $analisis = fixture_analisis_confirmado($entrega, 'OBSERVADA', $admin->id);

    expect($analisis->fresh()->resultado)->toBe('OBSERVADA');
    expect(LoteProduccionModel::count())->toBe(0);
});
