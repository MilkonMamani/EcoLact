<?php

use App\Application\Liquidacion\RegistrarReglaPagoCalidad;
use App\Domain\Shared\Enums\ResultadoCalidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('registra una regla de pago por calidad correctamente', function () {
    $regla = app(RegistrarReglaPagoCalidad::class)->execute(
        resultadoCalidad: ResultadoCalidad::OBSERVADA,
        tipoAjuste: 'MONTO',
        valorAjuste: -0.20,
        fechaInicio: new DateTimeImmutable('2026-01-01'),
        fechaFin: null,
        estado: 'ACTIVO',
    );

    expect($regla->id())->not->toBeNull();
    expect($regla->resultadoCalidad())->toBe(ResultadoCalidad::OBSERVADA);
    expect($regla->tipoAjuste())->toBe('MONTO');
    expect($regla->valorAjuste())->toBe(-0.20);
});

it('usa el monto/porcentaje exacto recibido del administrador, sin hardcodear valores', function () {
    // Valores deliberadamente distintos de cualquier ejemplo típico
    // (-0.20, -10%), para comprobar que el Use Case no los sustituye.
    $reglaMonto = app(RegistrarReglaPagoCalidad::class)->execute(
        resultadoCalidad: ResultadoCalidad::OBSERVADA,
        tipoAjuste: 'MONTO',
        valorAjuste: -0.37,
        fechaInicio: new DateTimeImmutable('2026-01-01'),
        fechaFin: null,
        estado: 'ACTIVO',
    );

    $reglaPorcentaje = app(RegistrarReglaPagoCalidad::class)->execute(
        resultadoCalidad: ResultadoCalidad::RECHAZADA,
        tipoAjuste: 'PORCENTAJE',
        valorAjuste: -85.5,
        fechaInicio: new DateTimeImmutable('2026-01-01'),
        fechaFin: null,
        estado: 'ACTIVO',
    );

    expect($reglaMonto->valorAjuste())->toBe(-0.37);
    expect($reglaPorcentaje->valorAjuste())->toBe(-85.5);
    expect($reglaPorcentaje->tipoAjuste())->toBe('PORCENTAJE');
});

it('rechaza una regla de pago cuya fecha fin es anterior a la fecha inicio', function () {
    app(RegistrarReglaPagoCalidad::class)->execute(
        resultadoCalidad: ResultadoCalidad::OBSERVADA,
        tipoAjuste: 'MONTO',
        valorAjuste: -0.20,
        fechaInicio: new DateTimeImmutable('2026-06-01'),
        fechaFin: new DateTimeImmutable('2026-01-01'),
        estado: 'ACTIVO',
    );
})->throws(DomainException::class, 'La fecha fin de la regla de pago no puede ser anterior a la fecha inicio.');
