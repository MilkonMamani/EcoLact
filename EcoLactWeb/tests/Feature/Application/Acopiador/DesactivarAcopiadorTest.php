<?php

use App\Application\Acopiador\DesactivarAcopiador;
use App\Application\Acopiador\RegistrarAcopiador;
use App\Domain\Shared\Enums\EstadoUsuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('desactiva un acopiador ACTIVO, lo deja INACTIVO y cierra su historial de zona vigente', function () {
    fixture_rol('ACOPIADOR');
    $zona = fixture_zona();

    $acopiador = app(RegistrarAcopiador::class)->execute(
        dni: '20202020',
        nombres: 'Teodoro',
        apellidos: 'Mamani',
        telefono: null,
        zonaId: $zona->id,
        username: 'teodoro.mamani',
        password: 'Secreto#123',
    );

    $this->assertDatabaseHas('acopiador_zona_historial', [
        'acopiador_id' => $acopiador->id(),
        'fecha_fin' => null,
    ]);

    $desactivado = app(DesactivarAcopiador::class)->execute($acopiador->id());

    expect($desactivado->estado())->toBe(EstadoUsuario::INACTIVO);

    $this->assertDatabaseMissing('acopiador_zona_historial', [
        'acopiador_id' => $acopiador->id(),
        'fecha_fin' => null,
    ]);
    // No se elimina nada: el acopiador, su usuario y su historial persisten.
    $this->assertDatabaseHas('acopiadores', ['id' => $acopiador->id()]);
    $this->assertDatabaseHas('users', ['id' => $acopiador->userId()]);
    $this->assertDatabaseCount('acopiador_zona_historial', 1);
});

it('rechaza desactivar un acopiador que ya está inactivo', function () {
    fixture_rol('ACOPIADOR');
    $zona = fixture_zona();

    $acopiador = app(RegistrarAcopiador::class)->execute(
        dni: '30303030',
        nombres: 'Beatriz',
        apellidos: 'Colque',
        telefono: null,
        zonaId: $zona->id,
        username: 'beatriz.colque',
        password: 'Secreto#123',
    );

    app(DesactivarAcopiador::class)->execute($acopiador->id());
    app(DesactivarAcopiador::class)->execute($acopiador->id());
})->throws(DomainException::class, 'El acopiador ya se encuentra inactivo.');

it('rechaza desactivar un acopiador que no existe', function () {
    app(DesactivarAcopiador::class)->execute(999999);
})->throws(DomainException::class, 'El acopiador indicado no existe.');
