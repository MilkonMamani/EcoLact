<?php

use App\Application\Comunicacion\CrearComunicado;
use App\Application\Comunicacion\PublicarComunicado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

it('publica un comunicado que estaba en BORRADOR', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Borrador a publicar',
        contenido: 'Contenido',
        tipo: 'INFORMACION',
        alcance: 'GENERAL',
        creadoPorUserId: $admin->id,
        estado: 'BORRADOR',
    );

    $publicado = app(PublicarComunicado::class)->execute($comunicado->id());

    expect($publicado->estado())->toBe('PUBLICADO');
});

it('no permite publicar un comunicado que ya está PUBLICADO', function () {
    $admin = fixture_usuario('ADMINISTRADOR');

    $comunicado = app(CrearComunicado::class)->execute(
        titulo: 'Ya publicado',
        contenido: 'Contenido',
        tipo: 'INFORMACION',
        alcance: 'GENERAL',
        creadoPorUserId: $admin->id,
    );

    app(PublicarComunicado::class)->execute($comunicado->id());
})->throws(DomainException::class);
