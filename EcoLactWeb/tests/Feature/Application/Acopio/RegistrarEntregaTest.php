<?php

use App\Application\Acopio\RegistrarEntrega;
use App\Domain\Shared\Enums\EstadoEntrega;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('registra correctamente una entrega válida cuando proveedor y acopiador comparten zona', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    $entrega = app(RegistrarEntrega::class)->execute(
        metodoIdentificacion: 'DNI',
        identificador: $proveedor->dni,
        acopiadorUserId: $acopiador->user_id,
        litros: 30.5,
    );

    expect($entrega->id())->not->toBeNull();
    expect($entrega->litros())->toBe(30.5);
    expect($entrega->estado())->toBe(EstadoEntrega::PENDIENTE_ANALISIS);
    expect($entrega->zonaId())->toBe($zona->id);

    $this->assertDatabaseHas('notificaciones', [
        'user_id' => $proveedor->user_id,
        'tipo' => 'ENTREGA_REGISTRADA',
    ]);
});

it('identifica al proveedor por su código QR estático', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    $entrega = app(RegistrarEntrega::class)->execute(
        metodoIdentificacion: 'QR',
        identificador: $proveedor->qr_token,
        acopiadorUserId: $acopiador->user_id,
        litros: 12,
    );

    expect($entrega->proveedorId())->toBe($proveedor->id);
});

it('no registra una entrega con litros menores o iguales a cero', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    app(RegistrarEntrega::class)->execute(
        metodoIdentificacion: 'DNI',
        identificador: $proveedor->dni,
        acopiadorUserId: $acopiador->user_id,
        litros: 0,
    );
})->throws(InvalidArgumentException::class);

it('un acopiador INACTIVO no puede registrar una entrega nueva', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);
    $acopiador->update(['estado' => 'INACTIVO']);

    app(RegistrarEntrega::class)->execute(
        metodoIdentificacion: 'DNI',
        identificador: $proveedor->dni,
        acopiadorUserId: $acopiador->user_id,
        litros: 20,
    );
})->throws(DomainException::class, 'El acopiador no está activo y no puede registrar entregas.');

it('un proveedor no habilitado (INACTIVO) no puede registrar una entrega', function () {
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $proveedor->update(['estado' => 'INACTIVO']);
    $acopiador = fixture_acopiador($zona);

    app(RegistrarEntrega::class)->execute(
        metodoIdentificacion: 'DNI',
        identificador: $proveedor->dni,
        acopiadorUserId: $acopiador->user_id,
        litros: 20,
    );
})->throws(DomainException::class, 'El proveedor no está habilitado para registrar entregas.');

it('un acopiador no puede registrar la entrega de un proveedor de otra zona', function () {
    $zonaProveedor = fixture_zona('Zona A');
    $zonaAcopiador = fixture_zona('Zona B');

    $proveedor = fixture_proveedor($zonaProveedor);
    $acopiador = fixture_acopiador($zonaAcopiador);

    app(RegistrarEntrega::class)->execute(
        metodoIdentificacion: 'DNI',
        identificador: $proveedor->dni,
        acopiadorUserId: $acopiador->user_id,
        litros: 20,
    );
})->throws(DomainException::class, 'El acopiador no puede registrar entregas de un proveedor de otra zona.');
