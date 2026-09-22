<?php

use App\Domain\Comunicacion\Notificacion;
use App\Infrastructure\Persistence\Models\RoleModel;
use App\Infrastructure\Persistence\Repositories\EloquentNotificacionRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function crearUsuarioParaNotificacion(): User
{
    $role = RoleModel::create(['nombre' => 'PROVEEDOR_NOTIF', 'descripcion' => null]);

    return User::create([
        'role_id' => $role->id,
        'username' => 'usuario.notificacion',
        'password' => 'secret',
        'estado' => 'ACTIVO',
    ]);
}

it('guarda una notificación y la mapea correctamente a la entidad de dominio', function () {
    $repository = new EloquentNotificacionRepository;
    $user = crearUsuarioParaNotificacion();

    $notificacion = new Notificacion(
        id: null,
        userId: $user->id,
        tipo: 'ENTREGA_REGISTRADA',
        titulo: 'Entrega registrada',
        mensaje: 'Se registró tu entrega de leche del día.',
        referenciaTipo: 'entrega',
        referenciaId: 15,
        leido: false,
        fechaLectura: null,
    );

    $guardada = $repository->guardar($notificacion);

    expect($guardada->id())->not->toBeNull();
    expect($guardada->leido())->toBeFalse();
    expect($guardada->fechaLectura())->toBeNull();

    expect($repository->listarParaUsuario($user->id))->toHaveCount(1);
});

it('marca una notificación como leída', function () {
    $repository = new EloquentNotificacionRepository;
    $user = crearUsuarioParaNotificacion();

    $guardada = $repository->guardar(new Notificacion(
        id: null,
        userId: $user->id,
        tipo: 'PAGO_REALIZADO',
        titulo: 'Pago realizado',
        mensaje: 'Se realizó el pago de tu liquidación.',
        referenciaTipo: 'pago',
        referenciaId: 7,
        leido: false,
    ));

    $marcada = $repository->marcarComoLeida($guardada->id());

    expect($marcada->leido())->toBeTrue();
    expect($marcada->fechaLectura())->toBeInstanceOf(DateTimeImmutable::class);
});
