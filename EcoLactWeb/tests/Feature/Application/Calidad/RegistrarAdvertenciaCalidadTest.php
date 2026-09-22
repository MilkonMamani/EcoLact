<?php

use App\Application\Calidad\FinalizarAnalisisCalidad;
use App\Application\Calidad\RegistrarAnalisisCalidad;
use App\Infrastructure\Persistence\Models\AnalisisCalidadModel;
use App\Infrastructure\Persistence\Models\NotificacionModel;
use App\Infrastructure\Persistence\Models\ProveedorModel;
use App\Infrastructure\Persistence\Models\SancionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/**
 * Crea un análisis BORRADOR con un único valor de parámetro y lo confirma
 * (a través de FinalizarAnalisisCalidad), forzando el resultado indicado
 * mediante una regla de calidad configurada para incumplir o cumplir según
 * corresponda.
 */
function raa_confirmarAnalisis(ProveedorModel $proveedor, $acopiador, string $resultadoEsperado, int $confirmadoPorUserId): AnalisisCalidadModel
{
    static $contador = 0;
    $contador++;

    $parametro = fixture_parametro('PARAM_ADV_'.$contador);

    // RECHAZADA y OBSERVADA se logran con dos reglas de distinta prioridad
    // sobre el mismo parámetro: la de mayor prioridad (número menor) gana.
    if ($resultadoEsperado === 'ACEPTADA') {
        fixture_regla_calidad($parametro->id, 0.0, 100.0, 'RECHAZADA', prioridad: 1);
    } else {
        fixture_regla_calidad($parametro->id, 50.0, 100.0, $resultadoEsperado, prioridad: 1);
    }

    $entrega = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable);

    $analisis = app(RegistrarAnalisisCalidad::class)->execute(
        entregaId: $entrega->id,
        valores: [$parametro->codigo => 10.0],
    );

    return AnalisisCalidadModel::find(
        app(FinalizarAnalisisCalidad::class)->execute($analisis->id(), $confirmadoPorUserId)->id()
    );
}

it('la primera advertencia de calidad notifica al proveedor sin desactivarlo', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    raa_confirmarAnalisis($proveedor, $acopiador, 'OBSERVADA', $admin->id);

    $proveedor->refresh();

    expect($proveedor->advertencias_count)->toBe(1);
    expect($proveedor->estado)->toBe('ACTIVO');
    expect(SancionModel::where('proveedor_id', $proveedor->id)->count())->toBe(1);
    expect(SancionModel::where('proveedor_id', $proveedor->id)->first()->nivel_advertencia)->toBe(1);
    expect(NotificacionModel::where('user_id', $proveedor->user_id)->where('tipo', 'ADVERTENCIA_CALIDAD')->exists())->toBeTrue();
});

it('la segunda advertencia notifica que es la última antes de desactivar', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    raa_confirmarAnalisis($proveedor, $acopiador, 'OBSERVADA', $admin->id);
    raa_confirmarAnalisis($proveedor, $acopiador, 'OBSERVADA', $admin->id);

    $proveedor->refresh();

    expect($proveedor->advertencias_count)->toBe(2);
    expect($proveedor->estado)->toBe('ACTIVO');
    expect(NotificacionModel::where('user_id', $proveedor->user_id)->where('tipo', 'ULTIMA_ADVERTENCIA_CALIDAD')->exists())->toBeTrue();
});

it('la tercera advertencia (observada o rechazada, cuentan igual) desactiva al proveedor automáticamente', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    raa_confirmarAnalisis($proveedor, $acopiador, 'OBSERVADA', $admin->id);
    raa_confirmarAnalisis($proveedor, $acopiador, 'OBSERVADA', $admin->id);
    raa_confirmarAnalisis($proveedor, $acopiador, 'RECHAZADA', $admin->id);

    $proveedor->refresh();

    expect($proveedor->advertencias_count)->toBe(3);
    expect($proveedor->estado)->toBe('INACTIVO');
    expect(SancionModel::where('proveedor_id', $proveedor->id)->where('nivel_advertencia', 3)->first()->tipo_penalizacion)->toBe('INACTIVACION');
    expect(NotificacionModel::where('user_id', $proveedor->user_id)->where('tipo', 'PROVEEDOR_INACTIVADO')->exists())->toBeTrue();
});

it('un análisis ACEPTADA no genera ninguna advertencia ni sanción', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $admin = fixture_usuario('ADMINISTRADOR');

    raa_confirmarAnalisis($proveedor, $acopiador, 'ACEPTADA', $admin->id);

    $proveedor->refresh();

    expect($proveedor->advertencias_count)->toBe(0);
    expect(SancionModel::where('proveedor_id', $proveedor->id)->count())->toBe(0);
});
