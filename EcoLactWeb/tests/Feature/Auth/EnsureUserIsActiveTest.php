<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('EnsureUserIsActive bloquea a un acopiador INACTIVO', function () {
    Route::middleware('active')->get('/_test/active-acopiador-inactivo', fn () => response('ok'));

    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $acopiador->update(['estado' => 'INACTIVO']);

    $response = $this->actingAs($acopiador->user)->get('/_test/active-acopiador-inactivo');

    $response->assertForbidden();
});

it('EnsureUserIsActive permite a un acopiador ACTIVO', function () {
    Route::middleware('active')->get('/_test/active-acopiador-activo', fn () => response('ok'));

    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);

    $response = $this->actingAs($acopiador->user)->get('/_test/active-acopiador-activo');

    $response->assertOk();
});

it('EnsureUserIsActive bloquea cuando users.estado es INACTIVO, aunque el acopiador siga ACTIVO', function () {
    Route::middleware('active')->get('/_test/active-user-inactivo', fn () => response('ok'));

    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $acopiador->user->update(['estado' => 'INACTIVO']);

    $response = $this->actingAs($acopiador->user)->get('/_test/active-user-inactivo');

    $response->assertForbidden();
});

it('EnsureUserIsActive no bloquea peticiones sin usuario autenticado (deja pasar al guard)', function () {
    Route::middleware('active')->get('/_test/active-guest', fn () => response('ok'));

    $response = $this->get('/_test/active-guest');

    $response->assertOk();
});
