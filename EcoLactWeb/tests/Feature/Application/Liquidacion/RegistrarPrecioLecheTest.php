<?php

use App\Application\Liquidacion\RegistrarPrecioLeche;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('registra un precio de leche con el valor exacto recibido del administrador', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $precio = app(RegistrarPrecioLeche::class)->execute(
        precioLitro: 1.75,
        fechaInicio: new DateTimeImmutable('2026-03-01'),
        fechaFin: null,
        estado: 'ACTIVO',
        creadoPorUserId: $admin->id,
    );

    expect($precio->id())->not->toBeNull();
    expect($precio->precioLitro())->toBe(1.75);
    expect($precio->creadoPorUserId())->toBe($admin->id);
});

it('rechaza registrar un precio cuando el usuario que lo crea no existe', function () {
    app(RegistrarPrecioLeche::class)->execute(
        precioLitro: 1.75,
        fechaInicio: new DateTimeImmutable('2026-03-01'),
        fechaFin: null,
        estado: 'ACTIVO',
        creadoPorUserId: 999999,
    );
})->throws(DomainException::class, 'El usuario que registra el precio no existe.');

it('rechaza un precio cuya fecha fin es anterior a la fecha inicio', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    app(RegistrarPrecioLeche::class)->execute(
        precioLitro: 1.75,
        fechaInicio: new DateTimeImmutable('2026-03-01'),
        fechaFin: new DateTimeImmutable('2026-01-01'),
        estado: 'ACTIVO',
        creadoPorUserId: $admin->id,
    );
})->throws(DomainException::class, 'La fecha fin del precio no puede ser anterior a la fecha inicio.');
