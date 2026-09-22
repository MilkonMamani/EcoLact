<?php

use App\Application\Liquidacion\ObtenerReglasPagoCalidad;
use App\Application\Liquidacion\RegistrarReglaPagoCalidad;
use App\Domain\Shared\Enums\ResultadoCalidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('lista todas las reglas de pago por calidad configuradas', function () {
    app(RegistrarReglaPagoCalidad::class)->execute(
        ResultadoCalidad::ACEPTADA, 'MONTO', 0.0, new DateTimeImmutable('2026-01-01'), null, 'ACTIVO',
    );
    app(RegistrarReglaPagoCalidad::class)->execute(
        ResultadoCalidad::OBSERVADA, 'MONTO', -0.20, new DateTimeImmutable('2026-01-01'), null, 'ACTIVO',
    );

    $reglas = app(ObtenerReglasPagoCalidad::class)->execute();

    expect($reglas)->toHaveCount(2);
});
