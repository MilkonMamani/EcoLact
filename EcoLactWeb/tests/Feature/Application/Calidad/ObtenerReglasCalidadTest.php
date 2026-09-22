<?php

use App\Application\Calidad\ObtenerReglasCalidad;
use App\Application\Calidad\ObtenerReglasCalidadPorParametro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('lista todas las reglas de calidad configuradas', function () {
    $grasa = fixture_parametro('GRASA');
    $ph = fixture_parametro('PH');
    fixture_regla_calidad($grasa->id, 3.0, 6.0, 'OBSERVADA');
    fixture_regla_calidad($ph->id, 6.2, 6.8, 'RECHAZADA');

    $reglas = app(ObtenerReglasCalidad::class)->execute();

    expect($reglas)->toHaveCount(2);
});

it('lista las reglas de calidad de un parámetro específico', function () {
    $grasa = fixture_parametro('GRASA');
    $ph = fixture_parametro('PH');
    fixture_regla_calidad($grasa->id, 3.0, 6.0, 'OBSERVADA');
    fixture_regla_calidad($ph->id, 6.2, 6.8, 'RECHAZADA');

    $reglasDeGrasa = app(ObtenerReglasCalidadPorParametro::class)->execute($grasa->id);

    expect($reglasDeGrasa)->toHaveCount(1);
    expect($reglasDeGrasa[0]->parametroId())->toBe($grasa->id);
});
