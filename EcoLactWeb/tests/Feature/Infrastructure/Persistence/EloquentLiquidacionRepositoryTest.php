<?php

use App\Domain\Pago\Liquidacion;
use App\Domain\Proveedor\Proveedor;
use App\Domain\Shared\Enums\EstadoLiquidacion;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Infrastructure\Persistence\Models\PeriodoLiquidacionModel;
use App\Infrastructure\Persistence\Models\RoleModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use App\Infrastructure\Persistence\Repositories\EloquentLiquidacionRepository;
use App\Infrastructure\Persistence\Repositories\EloquentProveedorRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function crearEscenarioLiquidacion(): array
{
    $role = RoleModel::create(['nombre' => 'ADMIN', 'descripcion' => null]);
    $user = User::create([
        'role_id' => $role->id,
        'username' => 'admin.liquidacion',
        'password' => 'secret',
        'estado' => 'ACTIVO',
    ]);
    $zona = ZonaModel::create(['nombre' => 'Zona Liquidacion', 'descripcion' => null, 'estado' => 'ACTIVO']);
    $proveedorUser = User::create([
        'role_id' => $role->id,
        'username' => 'proveedor.liquidacion',
        'password' => 'secret',
        'estado' => 'ACTIVO',
    ]);

    $proveedor = (new EloquentProveedorRepository)->guardar(new Proveedor(
        id: null,
        userId: $proveedorUser->id,
        zonaId: $zona->id,
        dni: '30304040',
        nombres: 'Ines',
        apellidos: 'Rojas',
        telefono: null,
        qrToken: 'qr-token-liquidacion',
        estado: EstadoUsuario::ACTIVO,
        fechaRegistro: new DateTimeImmutable('2026-01-01'),
    ));

    $periodo = PeriodoLiquidacionModel::create([
        'fecha_inicio' => '2026-06-01',
        'fecha_fin' => '2026-06-07',
        'estado' => 'ABIERTO',
    ]);

    return ['user' => $user, 'proveedor' => $proveedor, 'periodo' => $periodo];
}

it('guarda una liquidación y mapea los totales decimales como float', function () {
    $repository = new EloquentLiquidacionRepository;
    $escenario = crearEscenarioLiquidacion();

    $liquidacion = new Liquidacion(
        id: null,
        periodoId: $escenario['periodo']->id,
        proveedorId: $escenario['proveedor']->id(),
        totalLitrosEntregados: 500.0,
        totalLitrosPagables: 480.0,
        subtotal: 960.00,
        totalAjustes: -10.00,
        totalPagar: 950.00,
        estado: EstadoLiquidacion::PENDIENTE,
        fechaCalculo: new DateTimeImmutable('2026-06-08 10:00:00'),
        generadoPorUserId: $escenario['user']->id,
    );

    $guardada = $repository->guardar($liquidacion);

    expect($guardada->id())->not->toBeNull();
    expect($guardada->totalPagar())->toBeFloat();
    expect($guardada->totalPagar())->toBe(950.00);
    expect($guardada->coincideConTotalCalculado())->toBeTrue();

    expect($repository->buscarPorId($guardada->id())?->estado())->toBe(EstadoLiquidacion::PENDIENTE);
    expect($repository->listarPorProveedor($escenario['proveedor']->id()))->toHaveCount(1);
    expect($repository->listarPorPeriodo($escenario['periodo']->id))->toHaveCount(1);
});

it('actualiza el estado de una liquidación', function () {
    $repository = new EloquentLiquidacionRepository;
    $escenario = crearEscenarioLiquidacion();

    $guardada = $repository->guardar(new Liquidacion(
        id: null,
        periodoId: $escenario['periodo']->id,
        proveedorId: $escenario['proveedor']->id(),
        totalLitrosEntregados: 200.0,
        totalLitrosPagables: 200.0,
        subtotal: 400.0,
        totalAjustes: 0.0,
        totalPagar: 400.0,
        estado: EstadoLiquidacion::PENDIENTE,
        fechaCalculo: new DateTimeImmutable('2026-06-08 11:00:00'),
        generadoPorUserId: $escenario['user']->id,
    ));

    $actualizada = $repository->actualizar(new Liquidacion(
        id: $guardada->id(),
        periodoId: $escenario['periodo']->id,
        proveedorId: $escenario['proveedor']->id(),
        totalLitrosEntregados: 200.0,
        totalLitrosPagables: 200.0,
        subtotal: 400.0,
        totalAjustes: 0.0,
        totalPagar: 400.0,
        estado: EstadoLiquidacion::PARCIALMENTE_PAGADA,
        fechaCalculo: new DateTimeImmutable('2026-06-08 11:00:00'),
        generadoPorUserId: $escenario['user']->id,
    ));

    expect($actualizada->estado())->toBe(EstadoLiquidacion::PARCIALMENTE_PAGADA);
});
