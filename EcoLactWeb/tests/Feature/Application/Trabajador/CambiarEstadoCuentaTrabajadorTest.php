<?php

use App\Application\Acopiador\DesactivarAcopiador;
use App\Application\Acopiador\ReactivarAcopiador;
use App\Application\AnalistaCalidad\DesactivarAnalistaCalidad;
use App\Application\AnalistaCalidad\ReactivarAnalistaCalidad;
use App\Application\EncargadoPlanta\DesactivarEncargadoPlanta;
use App\Application\EncargadoPlanta\ReactivarEncargadoPlanta;
use App\Application\PersonalVentas\DesactivarPersonalVentas;
use App\Application\PersonalVentas\ReactivarPersonalVentas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-09-20 10:00:00');
});

it('desactivar al trabajador desactiva su usuario y reactivarlo lo reactiva, sin tocar la contraseña', function (string $rol, string $relacion, string $desactivar, string $reactivar) {
    $usuario = pagos_personal($rol, 'Persona');
    $fichaId = $usuario->{$relacion}->id;
    $zonaId = fixture_zona('Collana 1')->id;
    $reactivarFicha = fn () => $rol === 'ACOPIADOR'
        ? app($reactivar)->execute($fichaId, $zonaId)
        : app($reactivar)->execute($fichaId);

    app($desactivar)->execute($fichaId);

    expect($usuario->fresh()->estado)->toBe('INACTIVO')
        ->and($usuario->fresh()->{$relacion}->estado)->toBe('INACTIVO');
    // Desactivado: entra, pero solo para consultar su historial.
    $this->post('/login', ['username' => $usuario->username, 'password' => 'secret']);
    $this->assertAuthenticatedAs($usuario->fresh());
    expect($usuario->fresh()->estaEnSoloConsulta())->toBeTrue();
    $this->post('/logout');

    $reactivarFicha();

    expect($usuario->fresh()->estado)->toBe('ACTIVO')
        ->and($usuario->fresh()->{$relacion}->estado)->toBe('ACTIVO');
    $this->post('/login', ['username' => $usuario->username, 'password' => 'secret']);
    $this->assertAuthenticatedAs($usuario->fresh());
    expect($usuario->fresh()->estaOperativo())->toBeTrue();
})->with([
    'acopiador' => ['ACOPIADOR', 'acopiador', DesactivarAcopiador::class, ReactivarAcopiador::class],
    'analista de calidad' => ['CALIDAD', 'analistaCalidad', DesactivarAnalistaCalidad::class, ReactivarAnalistaCalidad::class],
    'encargado de planta' => ['ENCARGADO_PLANTA', 'encargadoPlanta', DesactivarEncargadoPlanta::class, ReactivarEncargadoPlanta::class],
    'personal de ventas' => ['VENTAS', 'personalVentas', DesactivarPersonalVentas::class, ReactivarPersonalVentas::class],
]);
