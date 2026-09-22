<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

// SESSION_DRIVER=array en testing no conserva datos flasheados entre
// llamadas HTTP independientes, por eso se usa followingRedirects() para
// que la redirección se resuelva dentro del mismo ciclo de request/response
// y el mensaje flash siga disponible al renderizar la página de destino.
it('el mensaje flash de éxito se muestra tras registrar una zona', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->from(route('admin.zonas.index'))->followingRedirects()->post(route('admin.zonas.store'), [
        'nombre' => 'Zona de prueba',
        'descripcion' => 'Descripción de prueba',
        'estado' => 'ACTIVO',
    ]);

    $response->assertOk();
    $response->assertSee('Zona registrada correctamente.');
});

it('los errores de validación se muestran junto al campo correspondiente', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $response = $this->actingAs($admin)->from(route('admin.zonas.index'))->followingRedirects()->post(route('admin.zonas.store'), [
        'descripcion' => 'Sin nombre',
        'estado' => 'ACTIVO',
    ]);

    $response->assertOk();
    $response->assertSee('The nombre field is required.');
});
