<?php

use App\Application\Calidad\CalcularResultadoCalidad;
use App\Application\Calidad\FinalizarAnalisisCalidad;
use App\Application\Calidad\RegistrarAnalisisCalidad;
use App\Domain\Entrega\EntregaRepository;
use App\Domain\Shared\Enums\EstadoAnalisis;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Domain\Shared\Enums\ResultadoCalidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('un análisis BORRADOR puede existir sin resultado definitivo', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);

    $parametro = fixture_parametro('GRASA');
    fixture_regla_calidad($parametro->id, 3.0, 6.0, 'OBSERVADA');

    $analisis = app(RegistrarAnalisisCalidad::class)->execute(
        entregaId: $entrega->id,
        valores: ['GRASA' => 3.5],
    );

    expect($analisis->estado())->toBe(EstadoAnalisis::BORRADOR);
    expect($analisis->resultado())->toBeNull();
    expect($analisis->estaConfirmado())->toBeFalse();

    $entregaSinConfirmar = app(EntregaRepository::class)->buscarPorId($entrega->id);
    expect($entregaSinConfirmar->estado())->toBe(EstadoEntrega::PENDIENTE_ANALISIS);
});

it('calcula el resultado de un análisis usando las reglas de calidad vigentes configuradas', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);

    $parametro = fixture_parametro('GRASA');
    fixture_regla_calidad($parametro->id, 3.0, 6.0, 'OBSERVADA');

    $analisis = app(RegistrarAnalisisCalidad::class)->execute(
        entregaId: $entrega->id,
        valores: ['GRASA' => 3.5],
    );

    $resultadoDentroDeRango = app(CalcularResultadoCalidad::class)->execute($analisis->id());
    expect($resultadoDentroDeRango)->toBe(ResultadoCalidad::ACEPTADA);

    app(RegistrarAnalisisCalidad::class)->execute(
        entregaId: $entrega->id,
        valores: ['GRASA' => 2.0],
    );

    $resultadoFueraDeRango = app(CalcularResultadoCalidad::class)->execute($analisis->id());
    expect($resultadoFueraDeRango)->toBe(ResultadoCalidad::OBSERVADA);
});

it('no calcula un resultado si faltan reglas de calidad configuradas para el parámetro analizado', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);

    fixture_parametro('LACTOSA');

    $analisis = app(RegistrarAnalisisCalidad::class)->execute(
        entregaId: $entrega->id,
        valores: ['LACTOSA' => 4.5],
    );

    app(CalcularResultadoCalidad::class)->execute($analisis->id());
})->throws(DomainException::class);

it('un análisis CONFIRMADO no puede modificarse', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);

    $parametro = fixture_parametro('GRASA');
    fixture_regla_calidad($parametro->id, 3.0, 6.0, 'OBSERVADA');

    $analisis = app(RegistrarAnalisisCalidad::class)->execute(
        entregaId: $entrega->id,
        valores: ['GRASA' => 3.5],
    );

    $confirmado = app(FinalizarAnalisisCalidad::class)->execute($analisis->id(), $acopiador->user_id);

    expect($confirmado->estado())->toBe(EstadoAnalisis::CONFIRMADO);
    expect($confirmado->resultado())->toBe(ResultadoCalidad::ACEPTADA);

    app(FinalizarAnalisisCalidad::class)->execute($analisis->id(), $acopiador->user_id);
})->throws(DomainException::class, 'Un análisis de calidad confirmado es inmutable y no puede modificarse.');

it('al confirmar el análisis, la entrega pasa de PENDIENTE_ANALISIS a ANALIZADA', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $entrega = fixture_entrega($proveedor, $acopiador, 30.0);

    $parametro = fixture_parametro('GRASA');
    fixture_regla_calidad($parametro->id, 3.0, 6.0, 'OBSERVADA');

    $entregaAntes = app(EntregaRepository::class)->buscarPorId($entrega->id);
    expect($entregaAntes->estado())->toBe(EstadoEntrega::PENDIENTE_ANALISIS);

    $analisis = app(RegistrarAnalisisCalidad::class)->execute(
        entregaId: $entrega->id,
        valores: ['GRASA' => 3.5],
    );

    app(FinalizarAnalisisCalidad::class)->execute($analisis->id(), $acopiador->user_id);

    $entregaDespues = app(EntregaRepository::class)->buscarPorId($entrega->id);
    expect($entregaDespues->estado())->toBe(EstadoEntrega::ANALIZADA);
});
