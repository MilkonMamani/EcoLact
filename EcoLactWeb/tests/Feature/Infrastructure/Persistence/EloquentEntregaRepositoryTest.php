<?php

use App\Domain\Entrega\Entrega;
use App\Domain\Proveedor\Proveedor;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Infrastructure\Persistence\Models\RoleModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use App\Infrastructure\Persistence\Repositories\EloquentEntregaRepository;
use App\Infrastructure\Persistence\Repositories\EloquentProveedorRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function crearEscenarioEntrega(): array
{
    $role = RoleModel::create(['nombre' => 'ADMIN', 'descripcion' => null]);
    $user = User::create([
        'role_id' => $role->id,
        'username' => 'operador.test',
        'password' => 'secret',
        'estado' => 'ACTIVO',
    ]);

    $zona = ZonaModel::create(['nombre' => 'Zona Entregas', 'descripcion' => null, 'estado' => 'ACTIVO']);

    $proveedorUser = User::create([
        'role_id' => $role->id,
        'username' => 'proveedor.entrega',
        'password' => 'secret',
        'estado' => 'ACTIVO',
    ]);

    $proveedor = (new EloquentProveedorRepository)->guardar(new Proveedor(
        id: null,
        userId: $proveedorUser->id,
        zonaId: $zona->id,
        dni: '99990000',
        nombres: 'Carla',
        apellidos: 'Vargas',
        telefono: null,
        qrToken: 'qr-token-entrega',
        estado: EstadoUsuario::ACTIVO,
        fechaRegistro: new DateTimeImmutable('2026-01-01'),
    ));

    return ['user' => $user, 'zona' => $zona, 'proveedor' => $proveedor];
}

it('guarda una entrega y la recupera mapeada con litros como float y estado como enum', function () {
    $repository = new EloquentEntregaRepository;
    $escenario = crearEscenarioEntrega();

    $entrega = new Entrega(
        id: null,
        proveedorId: $escenario['proveedor']->id(),
        acopiadorId: null,
        zonaId: $escenario['zona']->id,
        registradoPorUserId: $escenario['user']->id,
        fechaHora: new DateTimeImmutable('2026-05-01 08:30:00'),
        litros: 125.5,
        metodoIdentificacion: 'QR',
        estado: EstadoEntrega::PENDIENTE_ANALISIS,
        observacion: null,
    );

    $guardada = $repository->guardar($entrega);

    expect($guardada->id())->not->toBeNull();
    expect($guardada->litros())->toBeFloat();
    expect($guardada->litros())->toBe(125.5);
    expect($guardada->estado())->toBe(EstadoEntrega::PENDIENTE_ANALISIS);
    expect($guardada->fechaHora()->format('Y-m-d H:i:s'))->toBe('2026-05-01 08:30:00');

    expect($repository->buscarPorId($guardada->id())?->litros())->toBe(125.5);
    expect($repository->listarPorProveedor($escenario['proveedor']->id()))->toHaveCount(1);
    expect($repository->listarPorZona($escenario['zona']->id))->toHaveCount(1);
});

it('actualiza el estado de una entrega', function () {
    $repository = new EloquentEntregaRepository;
    $escenario = crearEscenarioEntrega();

    $guardada = $repository->guardar(new Entrega(
        id: null,
        proveedorId: $escenario['proveedor']->id(),
        acopiadorId: null,
        zonaId: $escenario['zona']->id,
        registradoPorUserId: $escenario['user']->id,
        fechaHora: new DateTimeImmutable('2026-05-02 09:00:00'),
        litros: 80.0,
        metodoIdentificacion: 'MANUAL',
        estado: EstadoEntrega::PENDIENTE_ANALISIS,
    ));

    $actualizada = $repository->actualizar(new Entrega(
        id: $guardada->id(),
        proveedorId: $escenario['proveedor']->id(),
        acopiadorId: null,
        zonaId: $escenario['zona']->id,
        registradoPorUserId: $escenario['user']->id,
        fechaHora: new DateTimeImmutable('2026-05-02 09:00:00'),
        litros: 80.0,
        metodoIdentificacion: 'MANUAL',
        estado: EstadoEntrega::ANULADA,
        observacion: 'Registro duplicado',
    ));

    expect($actualizada->estado())->toBe(EstadoEntrega::ANULADA);
    expect($actualizada->observacion())->toBe('Registro duplicado');
});
