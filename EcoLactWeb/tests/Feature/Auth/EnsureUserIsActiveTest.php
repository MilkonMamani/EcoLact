<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('EnsureUserIsActive deja consultar pero no registrar a un acopiador INACTIVO', function () {
    Route::middleware('active')->get('/_test/active-acopiador-inactivo', fn () => response('ok'));
    Route::middleware('active')->post('/_test/active-acopiador-inactivo', fn () => response('ok'));

    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $acopiador->update(['estado' => 'INACTIVO']);

    $this->actingAs($acopiador->user)->get('/_test/active-acopiador-inactivo')->assertOk();
    $this->actingAs($acopiador->user)->post('/_test/active-acopiador-inactivo')->assertForbidden();
});

it('EnsureUserIsActive permite a un acopiador ACTIVO', function () {
    Route::middleware('active')->get('/_test/active-acopiador-activo', fn () => response('ok'));

    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);

    $response = $this->actingAs($acopiador->user)->get('/_test/active-acopiador-activo');

    $response->assertOk();
});

it('EnsureUserIsActive bloquea acciones cuando users.estado es INACTIVO, aunque el acopiador siga ACTIVO', function () {
    Route::middleware('active')->patch('/_test/active-user-inactivo', fn () => response('ok'));

    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);
    $acopiador->user->update(['estado' => 'INACTIVO']);

    $response = $this->actingAs($acopiador->user)->patch('/_test/active-user-inactivo');

    $response->assertForbidden();
});

it('EnsureUserIsActive bloquea por completo a un administrador INACTIVO', function () {
    Route::middleware('active')->get('/_test/active-admin-inactivo', fn () => response('ok'));

    $admin = fixture_usuario('ADMINISTRADOR');
    $admin->update(['estado' => 'INACTIVO']);

    $this->actingAs($admin)->get('/_test/active-admin-inactivo')->assertForbidden();
});

it('EnsureUserIsActive no bloquea peticiones sin usuario autenticado (deja pasar al guard)', function () {
    Route::middleware('active')->get('/_test/active-guest', fn () => response('ok'));

    $response = $this->get('/_test/active-guest');

    $response->assertOk();
});
