<?php

use App\Application\Liquidacion\ActualizarPrecioLeche;
use App\Application\Liquidacion\RegistrarPrecioLeche;
use App\Application\Shared\CampoActualizable;
use App\Domain\Pago\PrecioLecheRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('actualiza un precio de leche (cerrando su vigencia) sin destruir el historial anterior', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $precioAntiguo = app(RegistrarPrecioLeche::class)->execute(
        precioLitro: 1.60,
        fechaInicio: new DateTimeImmutable('2026-01-01'),
        fechaFin: null,
        estado: 'ACTIVO',
        creadoPorUserId: $admin->id,
    );

    $precioNuevo = app(RegistrarPrecioLeche::class)->execute(
        precioLitro: 1.75,
        fechaInicio: new DateTimeImmutable('2026-03-01'),
        fechaFin: null,
        estado: 'ACTIVO',
        creadoPorUserId: $admin->id,
    );

    $precioAntiguoCerrado = app(ActualizarPrecioLeche::class)->execute(
        precioLecheId: $precioAntiguo->id(),
        fechaFin: CampoActualizable::conValor(new DateTimeImmutable('2026-02-28')),
        estado: 'INACTIVO',
    );

    expect($precioAntiguoCerrado->fechaFin()?->format('Y-m-d'))->toBe('2026-02-28');
    expect($precioAntiguoCerrado->estado())->toBe('INACTIVO');
    expect($precioAntiguoCerrado->precioLitro())->toBe(1.60);

    // El precio nuevo (registrado antes de la actualización) sigue intacto:
    // actualizar uno no destruye ni reemplaza los demás registros históricos.
    $preciosLeche = app(PrecioLecheRepository::class);
    $precioNuevoIntacto = $preciosLeche->buscarPorId($precioNuevo->id());

    expect($precioNuevoIntacto)->not->toBeNull();
    expect($precioNuevoIntacto->precioLitro())->toBe(1.75);
    expect($precioNuevoIntacto->estado())->toBe('ACTIVO');

    $this->assertDatabaseCount('precios_leche', 2);
});

it('rechaza actualizar un precio de leche que no existe', function () {
    app(ActualizarPrecioLeche::class)->execute(precioLecheId: 999999, estado: 'INACTIVO');
})->throws(DomainException::class, 'El precio de leche indicado no existe.');

it('un campo omitido (fecha_fin no enviada) conserva su valor actual', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $precio = app(RegistrarPrecioLeche::class)->execute(
        precioLitro: 1.60,
        fechaInicio: new DateTimeImmutable('2026-01-01'),
        fechaFin: new DateTimeImmutable('2026-12-31'),
        estado: 'ACTIVO',
        creadoPorUserId: $admin->id,
    );

    // No se envía $fechaFin en absoluto: debe conservar 2026-12-31.
    $actualizado = app(ActualizarPrecioLeche::class)->execute(
        precioLecheId: $precio->id(),
        precioLitro: 1.65,
    );

    expect($actualizado->fechaFin()?->format('Y-m-d'))->toBe('2026-12-31');
    expect($actualizado->precioLitro())->toBe(1.65);
});

it('un campo enviado explícitamente como NULL realmente lo limpia (reabre la vigencia)', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $precio = app(RegistrarPrecioLeche::class)->execute(
        precioLitro: 1.60,
        fechaInicio: new DateTimeImmutable('2026-01-01'),
        fechaFin: new DateTimeImmutable('2026-12-31'),
        estado: 'ACTIVO',
        creadoPorUserId: $admin->id,
    );

    // Se envía fecha_fin explícitamente como null: debe quedar sin fecha fin.
    $actualizado = app(ActualizarPrecioLeche::class)->execute(
        precioLecheId: $precio->id(),
        fechaFin: CampoActualizable::conValor(null),
    );

    expect($actualizado->fechaFin())->toBeNull();
});

it('un campo enviado con un nuevo valor reemplaza el valor actual', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $precio = app(RegistrarPrecioLeche::class)->execute(
        precioLitro: 1.60,
        fechaInicio: new DateTimeImmutable('2026-01-01'),
        fechaFin: new DateTimeImmutable('2026-12-31'),
        estado: 'ACTIVO',
        creadoPorUserId: $admin->id,
    );

    $actualizado = app(ActualizarPrecioLeche::class)->execute(
        precioLecheId: $precio->id(),
        fechaFin: CampoActualizable::conValor(new DateTimeImmutable('2026-06-30')),
    );

    expect($actualizado->fechaFin()?->format('Y-m-d'))->toBe('2026-06-30');
});
