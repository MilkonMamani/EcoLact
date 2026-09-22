<?php

use App\Application\Liquidacion\ObtenerPreciosLeche;
use App\Application\Liquidacion\RegistrarPrecioLeche;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('lista todos los precios de leche configurados', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    app(RegistrarPrecioLeche::class)->execute(1.60, new DateTimeImmutable('2026-01-01'), null, 'ACTIVO', $admin->id);
    app(RegistrarPrecioLeche::class)->execute(1.75, new DateTimeImmutable('2026-03-01'), null, 'ACTIVO', $admin->id);

    $precios = app(ObtenerPreciosLeche::class)->execute();

    expect($precios)->toHaveCount(2);
});
