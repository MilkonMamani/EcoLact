<?php

use App\Application\Dashboard\DashboardPeriodo;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

it('resuelve la semana desde lunes incluso en domingo y al cambiar de año', function (string $fecha, string $desde, string $hasta) {
    $this->travelTo(CarbonImmutable::parse($fecha, 'UTC'));

    $periodo = DashboardPeriodo::resolver('semana');

    expect($periodo->limitesSql())->toBe([$desde.' 00:00:00', $hasta.' 23:59:59']);
})->with([
    'domingo' => ['2026-09-13 23:00:00', '2026-09-07', '2026-09-13'],
    'lunes' => ['2026-09-14 00:00:00', '2026-09-14', '2026-09-20'],
    'cambio de año' => ['2027-01-01 09:00:00', '2026-12-28', '2027-01-03'],
]);

it('incluye el último día de febrero bisiesto', function () {
    $this->travelTo(CarbonImmutable::parse('2028-02-10 12:00:00', 'UTC'));

    $periodo = DashboardPeriodo::resolver('mes');

    expect($periodo->limitesSql())->toBe(['2028-02-01 00:00:00', '2028-02-29 23:59:59']);
});

it('respeta la zona horaria configurada en el día y en el rango personalizado', function () {
    config(['app.timezone' => 'America/Lima']);
    $this->travelTo(CarbonImmutable::parse('2026-09-11 02:00:00', 'UTC'));

    $hoy = DashboardPeriodo::resolver('hoy');
    $personalizado = DashboardPeriodo::resolver('personalizado', '2026-09-01', '2026-09-02');

    expect($hoy->desde->toDateString())->toBe('2026-09-10');
    expect($hoy->desde->timezoneName)->toBe('America/Lima');
    expect($personalizado->desde->timezoneName)->toBe('America/Lima');
});
