<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('RoleMiddleware permite el acceso cuando el usuario tiene el rol correcto', function () {
    Route::middleware('role:ADMINISTRADOR')->get('/_test/role-admin-ok', fn () => response('ok'));

    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->get('/_test/role-admin-ok');

    $response->assertOk();
});

it('RoleMiddleware devuelve 403 cuando el usuario tiene un rol incorrecto', function () {
    Route::middleware('role:ADMINISTRADOR')->get('/_test/role-admin-forbidden', fn () => response('ok'));

    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);

    $response = $this->actingAs($acopiador->user)->get('/_test/role-admin-forbidden');

    $response->assertForbidden();
});

it('RoleMiddleware acepta una lista de roles separados por coma', function () {
    Route::middleware('role:ADMINISTRADOR,ACOPIADOR')->get('/_test/role-multi', fn () => response('ok'));

    $zona = fixture_zona();
    $acopiador = fixture_acopiador($zona);

    $response = $this->actingAs($acopiador->user)->get('/_test/role-multi');

    $response->assertOk();
});

it('RoleMiddleware devuelve 401 cuando no hay usuario autenticado', function () {
    Route::middleware('role:ADMINISTRADOR')->get('/_test/role-guest', fn () => response('ok'));

    $response = $this->get('/_test/role-guest');

    $response->assertUnauthorized();
});
