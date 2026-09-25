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

it('traduce el período a las semanas de proveedores cuyo pago programado cae dentro', function (string $fecha, string $periodo, ?array $semanas, ?string $unica) {
    $this->travelTo(CarbonImmutable::parse($fecha, config('app.timezone')));

    $resuelto = DashboardPeriodo::resolver($periodo);

    expect($resuelto->inicioSemanasProveedorPagaderas())->toBe($semanas);
    expect($resuelto->semanaProveedorUnica())->toBe($unica);
})->with([
    'jueves: se paga la semana anterior' => ['2026-09-10 08:00:00', 'hoy', ['2026-09-03', '2026-09-09'], '2026-09-03'],
    'viernes: no hay día de pago' => ['2026-09-11 08:00:00', 'hoy', null, null],
    'semana calendario con un jueves' => ['2026-09-13 08:00:00', 'semana', ['2026-09-03', '2026-09-09'], '2026-09-03'],
    'mes con cuatro jueves' => ['2026-09-10 08:00:00', 'mes', ['2026-08-27', '2026-09-23'], null],
]);

it('identifica el mes trabajado que se paga el día 1 incluido en el período', function (string $fecha, string $periodo, ?string $mes) {
    $this->travelTo(CarbonImmutable::parse($fecha, config('app.timezone')));

    expect(DashboardPeriodo::resolver($periodo)->mesTrabajadoUnico())->toBe($mes);
})->with([
    'mes' => ['2026-09-10 08:00:00', 'mes', '2026-08'],
    'enero paga diciembre' => ['2027-01-20 08:00:00', 'mes', '2026-12'],
    'semana con día 1' => ['2026-10-01 08:00:00', 'semana', '2026-09'],
    'semana sin día 1' => ['2026-09-10 08:00:00', 'semana', null],
    'hoy día 1' => ['2026-10-01 08:00:00', 'hoy', '2026-09'],
]);

it('marca como futuros solo los intervalos que aún no ocurren', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:30:00', config('app.timezone')));

    $horas = DashboardPeriodo::resolver('hoy')->intervalosGrafico();
    $dias = DashboardPeriodo::resolver('mes')->intervalosGrafico();

    expect(collect($horas)->where('futuro', false)->count())->toBe(13);
    expect($dias)->toHaveCount(30);
    expect($dias[9])->toMatchArray(['clave' => '2026-09-10', 'futuro' => false]);
    expect($dias[10])->toMatchArray(['clave' => '2026-09-11', 'futuro' => true]);
});
