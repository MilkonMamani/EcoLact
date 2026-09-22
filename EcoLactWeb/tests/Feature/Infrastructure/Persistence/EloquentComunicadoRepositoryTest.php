<?php

use App\Domain\Comunicacion\Comunicado;
use App\Infrastructure\Persistence\Models\ComunicadoDestinatarioModel;
use App\Infrastructure\Persistence\Models\RoleModel;
use App\Infrastructure\Persistence\Repositories\EloquentComunicadoRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function crearUsuarioParaComunicado(string $username): User
{
    $role = RoleModel::create(['nombre' => 'ADMIN_'.$username, 'descripcion' => null]);

    return User::create([
        'role_id' => $role->id,
        'username' => $username,
        'password' => 'secret',
        'estado' => 'ACTIVO',
    ]);
}

it('guarda un comunicado y lo mapea correctamente a la entidad de dominio', function () {
    $repository = new EloquentComunicadoRepository;
    $autor = crearUsuarioParaComunicado('autor.comunicado');

    $comunicado = new Comunicado(
        id: null,
        titulo: 'Mantenimiento programado',
        contenido: 'El acopio cerrará el sábado por mantenimiento.',
        tipo: 'AVISO',
        alcance: 'GENERAL',
        zonaId: null,
        fechaPublicacion: new DateTimeImmutable('2026-09-10 09:00:00'),
        fechaEvento: new DateTimeImmutable('2026-09-13 07:00:00'),
        creadoPorUserId: $autor->id,
        estado: 'PUBLICADO',
    );

    $guardado = $repository->guardar($comunicado);

    expect($guardado->id())->not->toBeNull();
    expect($guardado->titulo())->toBe('Mantenimiento programado');
    expect($guardado->fechaPublicacion())->toBeInstanceOf(DateTimeImmutable::class);
    expect($guardado->fechaEvento()?->format('Y-m-d H:i:s'))->toBe('2026-09-13 07:00:00');

    $encontrado = $repository->buscarPorId($guardado->id());
    expect($encontrado?->contenido())->toBe('El acopio cerrará el sábado por mantenimiento.');
});

it('lista los comunicados dirigidos a un usuario específico', function () {
    $repository = new EloquentComunicadoRepository;
    $autor = crearUsuarioParaComunicado('autor.destinatarios');
    $destinatario = crearUsuarioParaComunicado('destinatario.uno');
    $otroUsuario = crearUsuarioParaComunicado('destinatario.dos');

    $comunicado = $repository->guardar(new Comunicado(
        id: null,
        titulo: 'Comunicado dirigido',
        contenido: 'Contenido dirigido a un destinatario específico.',
        tipo: 'AVISO',
        alcance: 'INDIVIDUAL',
        zonaId: null,
        fechaPublicacion: new DateTimeImmutable('2026-09-10 10:00:00'),
        fechaEvento: null,
        creadoPorUserId: $autor->id,
        estado: 'PUBLICADO',
    ));

    ComunicadoDestinatarioModel::create([
        'comunicado_id' => $comunicado->id(),
        'user_id' => $destinatario->id,
        'leido' => false,
        'fecha_lectura' => null,
    ]);

    expect($repository->listarParaUsuario($destinatario->id))->toHaveCount(1);
    expect($repository->listarParaUsuario($otroUsuario->id))->toHaveCount(0);
});

it('actualiza un comunicado existente conservando su id', function () {
    $repository = new EloquentComunicadoRepository;
    $autor = crearUsuarioParaComunicado('autor.actualizar');

    $comunicado = $repository->guardar(new Comunicado(
        id: null,
        titulo: 'Borrador inicial',
        contenido: 'Contenido inicial.',
        tipo: 'AVISO',
        alcance: 'GENERAL',
        zonaId: null,
        fechaPublicacion: new DateTimeImmutable('2026-09-10 09:00:00'),
        fechaEvento: null,
        creadoPorUserId: $autor->id,
        estado: 'BORRADOR',
    ));

    $actualizado = $repository->actualizar(new Comunicado(
        id: $comunicado->id(),
        titulo: 'Título corregido',
        contenido: $comunicado->contenido(),
        tipo: $comunicado->tipo(),
        alcance: $comunicado->alcance(),
        zonaId: $comunicado->zonaId(),
        fechaPublicacion: $comunicado->fechaPublicacion(),
        fechaEvento: $comunicado->fechaEvento(),
        creadoPorUserId: $comunicado->creadoPorUserId(),
        estado: 'PUBLICADO',
    ));

    expect($actualizado->id())->toBe($comunicado->id());
    expect($actualizado->titulo())->toBe('Título corregido');
    expect($actualizado->estado())->toBe('PUBLICADO');
});

it('lista los destinatarios de un comunicado con su estado de lectura', function () {
    $repository = new EloquentComunicadoRepository;
    $autor = crearUsuarioParaComunicado('autor.lectura');
    $destinatario = crearUsuarioParaComunicado('destinatario.lectura');

    $comunicado = $repository->guardar(new Comunicado(
        id: null,
        titulo: 'Comunicado con lectura',
        contenido: 'Contenido.',
        tipo: 'AVISO',
        alcance: 'INDIVIDUAL',
        zonaId: null,
        fechaPublicacion: new DateTimeImmutable('2026-09-10 09:00:00'),
        fechaEvento: null,
        creadoPorUserId: $autor->id,
        estado: 'PUBLICADO',
    ));

    ComunicadoDestinatarioModel::create([
        'comunicado_id' => $comunicado->id(),
        'user_id' => $destinatario->id,
        'leido' => true,
        'fecha_lectura' => new DateTimeImmutable('2026-09-11 08:00:00'),
    ]);

    $destinatarios = $repository->listarDestinatarios($comunicado->id());

    expect($destinatarios)->toHaveCount(1);
    expect($destinatarios[0]['userId'])->toBe($destinatario->id);
    expect($destinatarios[0]['leido'])->toBeTrue();
    expect($destinatarios[0]['fechaLectura'])->toBeInstanceOf(DateTimeImmutable::class);
});
