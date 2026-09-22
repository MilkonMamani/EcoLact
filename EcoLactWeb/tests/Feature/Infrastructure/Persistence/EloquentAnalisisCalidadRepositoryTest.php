<?php

use App\Domain\Calidad\AnalisisCalidad;
use App\Domain\Entrega\Entrega;
use App\Domain\Proveedor\Proveedor;
use App\Domain\Shared\Enums\EstadoAnalisis;
use App\Domain\Shared\Enums\EstadoEntrega;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Domain\Shared\Enums\ResultadoCalidad;
use App\Infrastructure\Persistence\Models\RoleModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use App\Infrastructure\Persistence\Repositories\EloquentAnalisisCalidadRepository;
use App\Infrastructure\Persistence\Repositories\EloquentEntregaRepository;
use App\Infrastructure\Persistence\Repositories\EloquentProveedorRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function crearEntregaParaAnalisis(): Entrega
{
    $role = RoleModel::create(['nombre' => 'ADMIN', 'descripcion' => null]);
    $user = User::create([
        'role_id' => $role->id,
        'username' => 'operador.analisis',
        'password' => 'secret',
        'estado' => 'ACTIVO',
    ]);
    $zona = ZonaModel::create(['nombre' => 'Zona Calidad', 'descripcion' => null, 'estado' => 'ACTIVO']);
    $proveedorUser = User::create([
        'role_id' => $role->id,
        'username' => 'proveedor.analisis',
        'password' => 'secret',
        'estado' => 'ACTIVO',
    ]);

    $proveedor = (new EloquentProveedorRepository)->guardar(new Proveedor(
        id: null,
        userId: $proveedorUser->id,
        zonaId: $zona->id,
        dni: '10203040',
        nombres: 'Luis',
        apellidos: 'Choque',
        telefono: null,
        qrToken: 'qr-token-analisis',
        estado: EstadoUsuario::ACTIVO,
        fechaRegistro: new DateTimeImmutable('2026-01-01'),
    ));

    return (new EloquentEntregaRepository)->guardar(new Entrega(
        id: null,
        proveedorId: $proveedor->id(),
        acopiadorId: null,
        zonaId: $zona->id,
        registradoPorUserId: $user->id,
        fechaHora: new DateTimeImmutable('2026-06-01 07:00:00'),
        litros: 100.0,
        metodoIdentificacion: 'QR',
        estado: EstadoEntrega::PENDIENTE_ANALISIS,
    ));
}

it('persiste un análisis en borrador con resultado nulo y lo recupera como null', function () {
    $repository = new EloquentAnalisisCalidadRepository;
    $entrega = crearEntregaParaAnalisis();

    $guardado = $repository->guardar(new AnalisisCalidad(
        id: null,
        entregaId: $entrega->id(),
        imagenPath: null,
        estado: EstadoAnalisis::BORRADOR,
        resultado: null,
        fechaAnalisis: new DateTimeImmutable('2026-06-01 07:05:00'),
        confirmadoPorUserId: null,
    ));

    expect($guardado->id())->not->toBeNull();
    expect($guardado->resultado())->toBeNull();
    expect($guardado->estado())->toBe(EstadoAnalisis::BORRADOR);

    $encontrado = $repository->buscarPorId($guardado->id());
    expect($encontrado?->resultado())->toBeNull();

    $porEntrega = $repository->buscarPorEntregaId($entrega->id());
    expect($porEntrega?->id())->toBe($guardado->id());
});

it('persiste y recupera un análisis confirmado con resultado', function () {
    $repository = new EloquentAnalisisCalidadRepository;
    $entrega = crearEntregaParaAnalisis();

    $guardado = $repository->guardar(new AnalisisCalidad(
        id: null,
        entregaId: $entrega->id(),
        imagenPath: 'analisis/foto.jpg',
        estado: EstadoAnalisis::CONFIRMADO,
        resultado: ResultadoCalidad::ACEPTADA,
        fechaAnalisis: new DateTimeImmutable('2026-06-01 07:10:00'),
        confirmadoPorUserId: null,
        observacion: 'Todo en regla',
    ));

    $encontrado = $repository->buscarPorId($guardado->id());

    expect($encontrado?->resultado())->toBe(ResultadoCalidad::ACEPTADA);
    expect($encontrado?->estado())->toBe(EstadoAnalisis::CONFIRMADO);
    expect($encontrado?->imagenPath())->toBe('analisis/foto.jpg');
    expect($encontrado?->observacion())->toBe('Todo en regla');
});

function crearOtraEntregaParaAnalisis(): Entrega
{
    $role = RoleModel::firstOrCreate(['nombre' => 'ADMIN'], ['descripcion' => null]);
    $user = User::create([
        'role_id' => $role->id,
        'username' => 'operador.analisis2',
        'password' => 'secret',
        'estado' => 'ACTIVO',
    ]);
    $zona = ZonaModel::create(['nombre' => 'Zona Calidad 2', 'descripcion' => null, 'estado' => 'ACTIVO']);
    $proveedorUser = User::create([
        'role_id' => $role->id,
        'username' => 'proveedor.analisis2',
        'password' => 'secret',
        'estado' => 'ACTIVO',
    ]);

    $proveedor = (new EloquentProveedorRepository)->guardar(new Proveedor(
        id: null,
        userId: $proveedorUser->id,
        zonaId: $zona->id,
        dni: '50607080',
        nombres: 'Ana',
        apellidos: 'Vargas',
        telefono: null,
        qrToken: 'qr-token-analisis-2',
        estado: EstadoUsuario::ACTIVO,
        fechaRegistro: new DateTimeImmutable('2026-01-01'),
    ));

    return (new EloquentEntregaRepository)->guardar(new Entrega(
        id: null,
        proveedorId: $proveedor->id(),
        acopiadorId: null,
        zonaId: $zona->id,
        registradoPorUserId: $user->id,
        fechaHora: new DateTimeImmutable('2026-06-02 07:00:00'),
        litros: 80.0,
        metodoIdentificacion: 'QR',
        estado: EstadoEntrega::PENDIENTE_ANALISIS,
    ));
}

it('lista todos los análisis ordenados por fecha de análisis descendente', function () {
    $repository = new EloquentAnalisisCalidadRepository;
    $entregaA = crearEntregaParaAnalisis();
    $entregaB = crearOtraEntregaParaAnalisis();

    $repository->guardar(new AnalisisCalidad(
        id: null,
        entregaId: $entregaA->id(),
        imagenPath: null,
        estado: EstadoAnalisis::CONFIRMADO,
        resultado: ResultadoCalidad::ACEPTADA,
        fechaAnalisis: new DateTimeImmutable('2026-06-01 07:00:00'),
        confirmadoPorUserId: null,
    ));

    $masReciente = $repository->guardar(new AnalisisCalidad(
        id: null,
        entregaId: $entregaB->id(),
        imagenPath: null,
        estado: EstadoAnalisis::CONFIRMADO,
        resultado: ResultadoCalidad::OBSERVADA,
        fechaAnalisis: new DateTimeImmutable('2026-06-02 07:00:00'),
        confirmadoPorUserId: null,
    ));

    $lista = $repository->listar();

    expect($lista)->toHaveCount(2);
    expect($lista[0]->id())->toBe($masReciente->id());
});
