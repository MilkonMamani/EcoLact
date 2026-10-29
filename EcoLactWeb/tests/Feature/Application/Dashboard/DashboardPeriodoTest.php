<?php

use App\Application\Dashboard\DashboardPeriodo;
use Carbon\CarbonImmutable;
use Tests\TestCase;

uses(TestCase::class);

it('resuelve la semana de lunes a domingo del negocio con límites semiabiertos en UTC', function (string $fecha, string $desde, string $hasta) {
    $this->travelTo(CarbonImmutable::parse($fecha, 'America/Lima'));

    $periodo = DashboardPeriodo::resolver('semana');

    expect($periodo->limitesFecha())->toBe([$desde, $hasta]);
    expect($periodo->limitesSql())->toBe([$desde.' 05:00:00', CarbonImmutable::parse($hasta)->addDay()->toDateString().' 05:00:00']);
})->with([
    'domingo' => ['2026-09-13 23:00:00', '2026-09-07', '2026-09-13'],
    'lunes' => ['2026-09-14 00:00:00', '2026-09-14', '2026-09-20'],
    'cambio de año' => ['2027-01-01 09:00:00', '2026-12-28', '2027-01-03'],
]);

it('incluye el último día de febrero bisiesto y termina en el 1 de marzo excluido', function () {
    $this->travelTo(CarbonImmutable::parse('2028-02-10 12:00:00', 'America/Lima'));

    $periodo = DashboardPeriodo::resolver('mes');

    expect($periodo->limitesFecha())->toBe(['2028-02-01', '2028-02-29']);
    expect($periodo->limitesSql())->toBe(['2028-02-01 05:00:00', '2028-03-01 05:00:00']);
});

it('usa el día de Lima aunque en UTC ya sea el día siguiente', function () {
    // 28/10/2026 20:30 en Lima = 29/10/2026 01:30 UTC.
    $this->travelTo(CarbonImmutable::parse('2026-10-29 01:30:00', 'UTC'));

    $hoy = DashboardPeriodo::resolver('hoy');
    $personalizado = DashboardPeriodo::resolver('personalizado', '2026-09-01', '2026-09-02');

    expect($hoy->limitesFecha())->toBe(['2026-10-28', '2026-10-28']);
    expect($hoy->limitesSql())->toBe(['2026-10-28 05:00:00', '2026-10-29 05:00:00']);
    expect($hoy->desde->timezoneName)->toBe('America/Lima');
    expect($hoy->fechaCorte()->toDateString())->toBe('2026-10-28');
    expect($personalizado->desde->timezoneName)->toBe('America/Lima');
    expect(DashboardPeriodo::momentoLocal('2026-10-29 04:59:59')->toDateTimeString())->toBe('2026-10-28 23:59:59');
});

it('corta los saldos en el último día transcurrido del período', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-28 10:00:00', 'America/Lima'));

    expect(DashboardPeriodo::resolver('mes')->fechaCorte()->toDateString())->toBe('2026-10-28');
    expect(DashboardPeriodo::resolver('personalizado', '2026-09-01', '2026-09-15')->fechaCorte()->toDateString())->toBe('2026-09-15');
    expect(DashboardPeriodo::resolver('personalizado', '2026-12-01', '2026-12-05')->fechaCorte()->toDateString())->toBe('2026-10-28');
});

it('identifica la semana de proveedores que se paga en el período (el miércoles de cierre)', function (string $fecha, string $periodo, ?string $unica) {
    $this->travelTo(CarbonImmutable::parse($fecha, 'America/Lima'));

    expect(DashboardPeriodo::resolver($periodo)->semanaProveedorUnica())->toBe($unica);
})->with([
    'miércoles 28/10: se paga la semana 22/10-28/10' => ['2026-10-28 08:00:00', 'hoy', '2026-10-22'],
    'jueves: no hay día de pago' => ['2026-10-29 08:00:00', 'hoy', null],
    'semana calendario con un miércoles' => ['2026-10-28 08:00:00', 'semana', '2026-10-22'],
    'mes con varios miércoles' => ['2026-10-28 08:00:00', 'mes', null],
]);

it('identifica el mes trabajado que se paga el día 1 incluido en el período', function (string $fecha, string $periodo, ?string $mes) {
    $this->travelTo(CarbonImmutable::parse($fecha, 'America/Lima'));

    expect(DashboardPeriodo::resolver($periodo)->mesTrabajadoUnico())->toBe($mes);
})->with([
    'mes' => ['2026-09-10 08:00:00', 'mes', '2026-08'],
    'enero paga diciembre' => ['2027-01-20 08:00:00', 'mes', '2026-12'],
    'semana con día 1' => ['2026-10-01 08:00:00', 'semana', '2026-09'],
    'semana sin día 1' => ['2026-09-10 08:00:00', 'semana', null],
    'hoy día 1' => ['2026-10-01 08:00:00', 'hoy', '2026-09'],
]);

it('marca como futuros solo los intervalos que aún no ocurren', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-10 12:30:00', 'America/Lima'));

    $horas = DashboardPeriodo::resolver('hoy')->intervalosGrafico();
    $dias = DashboardPeriodo::resolver('mes')->intervalosGrafico();

    expect(collect($horas)->where('futuro', false)->count())->toBe(13);
    expect($dias)->toHaveCount(30);
    expect($dias[9])->toMatchArray(['clave' => '2026-09-10', 'futuro' => false]);
    expect($dias[10])->toMatchArray(['clave' => '2026-09-11', 'futuro' => true]);
});
