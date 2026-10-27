<?php

use App\Application\AnalistaCalidad\DesactivarAnalistaCalidad;
use App\Application\AnalistaCalidad\ReactivarAnalistaCalidad;
use App\Application\Proveedor\DesactivarProveedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

require_once __DIR__.'/../../Support/PagosFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

// Estas pruebas no tratan de asistencia: el personal ya tiene su jornada PRESENTE de hoy.
beforeEach(fn () => fixture_personal_con_asistencia_hoy());

/*
 * Solo el administrador desactiva. Un trabajador o proveedor desactivado queda en
 * "solo consulta": puede entrar y ver todo lo que hizo antes, pero no registrar,
 * editar ni eliminar nada. Al reactivarlo vuelve a operar con normalidad.
 */

const MENSAJE_SOLO_CONSULTA = 'Tu cuenta está desactivada: solo puedes consultar tu historial. Comunícate con el administrador para reactivarla.';

it('un trabajador desactivado sigue entrando para consultar y ve el aviso de solo consulta', function () {
    $analista = pagos_personal('CALIDAD', 'David');

    app(DesactivarAnalistaCalidad::class)->execute($analista->analistaCalidad->id);

    // Misma sesión autenticada; en una petición real el usuario se relee de la BD.
    $this->actingAs($analista->fresh())->get(route('calidad.dashboard'))
        ->assertOk()
        ->assertSee(MENSAJE_SOLO_CONSULTA);
    $this->assertAuthenticated();
});

it('un trabajador desactivado no puede registrar nada en web ni en la app', function (string $rol, string $ruta) {
    $usuario = pagos_personal($rol, 'Persona', estado: 'INACTIVO');

    $this->actingAs($usuario)->post(route($ruta), [])
        ->assertForbidden()
        ->assertSee(MENSAJE_SOLO_CONSULTA);
    $this->assertAuthenticated();

    $this->actingAs($usuario, 'sanctum')->postJson(route('api.'.$ruta), [])
        ->assertForbidden()
        ->assertJsonPath('message', MENSAJE_SOLO_CONSULTA);
})->with([
    'acopiador registra entrega' => ['ACOPIADOR', 'acopiador.entregas.store'],
    'analista registra análisis' => ['CALIDAD', 'calidad.analisis-calidad.store'],
    'encargado registra producción' => ['ENCARGADO_PLANTA', 'planta.produccion.store'],
    'personal de ventas registra venta' => ['VENTAS', 'ventas.ventas.store'],
]);

it('un trabajador con la ficha inactiva queda en solo consulta aunque su usuario siga activo', function () {
    $encargado = pagos_personal('ENCARGADO_PLANTA', 'Abel');
    $encargado->encargadoPlanta->update(['estado' => 'INACTIVO']);

    expect($encargado->fresh()->estado)->toBe('ACTIVO');
    $this->actingAs($encargado->fresh())->get(route('planta.dashboard'))->assertOk()->assertSee(MENSAJE_SOLO_CONSULTA);
    $this->actingAs($encargado->fresh())->post(route('planta.produccion.store'), [])->assertForbidden();
});

it('un proveedor desactivado conserva su token y consulta su historial en la app y la web', function () {
    $proveedor = fixture_proveedor(fixture_zona('Zona 1'), '01345678');
    $usuario = $proveedor->user;
    $token = $usuario->createToken('app')->plainTextToken;

    app(DesactivarProveedor::class)->execute($proveedor->id);

    $this->withToken($token)->getJson(route('api.proveedor.perfil'))->assertOk();
    $this->withToken($token)->getJson('/api/v1/me')->assertJsonPath('data.solo_consulta', true);
    expect(PersonalAccessToken::count())->toBe(1);

    $this->actingAs($usuario->fresh())->get(route('proveedor.entregas.index'))->assertOk()->assertSee(MENSAJE_SOLO_CONSULTA);
});

it('el login de un trabajador desactivado entra en solo consulta', function () {
    $analista = pagos_personal('CALIDAD', 'David');
    app(DesactivarAnalistaCalidad::class)->execute($analista->analistaCalidad->id);

    $this->post('/login', ['username' => $analista->username, 'password' => 'secret'])
        ->assertRedirectToRoute('calidad.dashboard');
    $this->assertAuthenticated();
});

it('al reactivarlo vuelve a poder registrar y desaparece el aviso', function () {
    $analista = pagos_personal('CALIDAD', 'David');
    app(DesactivarAnalistaCalidad::class)->execute($analista->analistaCalidad->id);
    app(ReactivarAnalistaCalidad::class)->execute($analista->analistaCalidad->id);

    $this->actingAs($analista->fresh())->get(route('calidad.dashboard'))->assertOk()->assertDontSee(MENSAJE_SOLO_CONSULTA);
    $this->actingAs($analista->fresh())->post(route('calidad.analisis-calidad.store'), [])->assertSessionHasErrors();
});

it('una cuenta sin ficha real sigue sin poder entrar', function () {
    $usuario = fixture_usuario('ACOPIADOR');

    $this->actingAs($usuario)->get(route('acopiador.dashboard'))
        ->assertForbidden()
        ->assertSee('Acceso no permitido');
    $this->assertGuest();
});

it('solo el administrador puede desactivar: ningún otro rol puede', function (string $rol) {
    $usuario = fixture_usuario($rol);
    $acopiador = fixture_acopiador(fixture_zona('Zona 1'));

    $this->actingAs($usuario)
        ->patchJson(route('admin.acopiadores.desactivar', $acopiador->id))
        ->assertForbidden();

    expect($acopiador->fresh()->estado)->toBe('ACTIVO');
})->with(['CALIDAD', 'ENCARGADO_PLANTA', 'VENTAS']);
