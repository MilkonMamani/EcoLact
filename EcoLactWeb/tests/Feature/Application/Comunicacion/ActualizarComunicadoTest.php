<?php

use App\Application\Comunicacion\ActualizarComunicado;
use App\Application\Comunicacion\CrearComunicado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('edita un comunicado mientras está en BORRADOR', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Título original',
        contenido: 'Contenido original',
        tipo: 'INFORMACION',
        alcance: 'GENERAL',
        creadoPorUserId: $admin->id,
        estado: 'BORRADOR',
    );

    $actualizado = app(ActualizarComunicado::class)->execute(
        comunicadoId: $comunicado->id(),
        titulo: 'Título corregido',
        tipo: 'AVISO',
    );

    expect($actualizado->titulo())->toBe('Título corregido');
    expect($actualizado->tipo())->toBe('AVISO');
    expect($actualizado->contenido())->toBe('Contenido original');
    expect($actualizado->estado())->toBe('BORRADOR');
});

it('no permite editar un comunicado ya PUBLICADO', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Título original',
        contenido: 'Contenido original',
        tipo: 'INFORMACION',
        alcance: 'GENERAL',
        creadoPorUserId: $admin->id,
    );

    app(ActualizarComunicado::class)->execute(
        comunicadoId: $comunicado->id(),
        titulo: 'Intento de edición',
    );
})->throws(DomainException::class);
