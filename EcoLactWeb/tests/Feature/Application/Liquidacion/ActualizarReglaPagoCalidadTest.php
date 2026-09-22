<?php

use App\Application\Liquidacion\ActualizarReglaPagoCalidad;
use App\Application\Liquidacion\RegistrarReglaPagoCalidad;
use App\Domain\Shared\Enums\ResultadoCalidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('actualiza una regla de pago por calidad existente con los nuevos valores recibidos', function () {
    $regla = app(RegistrarReglaPagoCalidad::class)->execute(
        resultadoCalidad: ResultadoCalidad::OBSERVADA,
        tipoAjuste: 'MONTO',
        valorAjuste: -0.20,
        fechaInicio: new DateTimeImmutable('2026-01-01'),
        fechaFin: null,
        estado: 'ACTIVO',
    );

    $actualizada = app(ActualizarReglaPagoCalidad::class)->execute(
        reglaId: $regla->id(),
        tipoAjuste: 'PORCENTAJE',
        valorAjuste: -12.5,
    );

    expect($actualizada->tipoAjuste())->toBe('PORCENTAJE');
    expect($actualizada->valorAjuste())->toBe(-12.5);
    // Los campos no enviados conservan su valor original.
    expect($actualizada->resultadoCalidad())->toBe(ResultadoCalidad::OBSERVADA);
    expect($actualizada->estado())->toBe('ACTIVO');
});

it('rechaza actualizar una regla de pago que no existe', function () {
    app(ActualizarReglaPagoCalidad::class)->execute(reglaId: 999999, estado: 'INACTIVO');
})->throws(DomainException::class, 'La regla de pago por calidad indicada no existe.');
