<?php

use App\Domain\Pago\Liquidacion;
use App\Domain\Pago\PagoProveedor;
use App\Domain\Proveedor\Proveedor;
use App\Domain\Shared\Enums\EstadoLiquidacion;
use App\Domain\Shared\Enums\EstadoPago;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Infrastructure\Persistence\Models\PeriodoLiquidacionModel;
use App\Infrastructure\Persistence\Models\RoleModel;
use App\Infrastructure\Persistence\Models\ZonaModel;
use App\Infrastructure\Persistence\Repositories\EloquentLiquidacionRepository;
use App\Infrastructure\Persistence\Repositories\EloquentPagoProveedorRepository;
use App\Infrastructure\Persistence\Repositories\EloquentProveedorRepository;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function crearLiquidacionParaPago(): array
{
    $role = RoleModel::create(['nombre' => 'ADMIN', 'descripcion' => null]);
    $user = User::create([
        'role_id' => $role->id,
        'username' => 'admin.pago',
        'password' => 'secret',
        'estado' => 'ACTIVO',
    ]);
    $zona = ZonaModel::create(['nombre' => 'Zona Pago', 'descripcion' => null, 'estado' => 'ACTIVO']);
    $proveedorUser = User::create([
        'role_id' => $role->id,
        'username' => 'proveedor.pago',
        'password' => 'secret',
        'estado' => 'ACTIVO',
    ]);

    $proveedor = (new EloquentProveedorRepository)->guardar(new Proveedor(
        id: null,
        userId: $proveedorUser->id,
        zonaId: $zona->id,
        dni: '50506060',
        nombres: 'Ana',
        apellidos: 'Ticona',
        telefono: null,
        qrToken: 'qr-token-pago',
        estado: EstadoUsuario::ACTIVO,
        fechaRegistro: new DateTimeImmutable('2026-01-01'),
    ));

    $periodo = PeriodoLiquidacionModel::create([
        'fecha_inicio' => '2026-07-01',
        'fecha_fin' => '2026-07-07',
        'estado' => 'ABIERTO',
    ]);

    $liquidacion = (new EloquentLiquidacionRepository)->guardar(new Liquidacion(
        id: null,
        periodoId: $periodo->id,
        proveedorId: $proveedor->id(),
        totalLitrosEntregados: 300.0,
        totalLitrosPagables: 300.0,
        subtotal: 600.0,
        totalAjustes: 0.0,
        totalPagar: 600.0,
        estado: EstadoLiquidacion::PENDIENTE,
        fechaCalculo: new DateTimeImmutable('2026-07-08 10:00:00'),
        generadoPorUserId: $user->id,
    ));

    return ['user' => $user, 'liquidacion' => $liquidacion];
}

it('guarda un pago y lo mapea con el monto como float', function () {
    $repository = new EloquentPagoProveedorRepository;
    $escenario = crearLiquidacionParaPago();

    $pago = new PagoProveedor(
        id: null,
        liquidacionId: $escenario['liquidacion']->id(),
        fechaPago: new DateTimeImmutable('2026-07-09 15:00:00'),
        monto: 600.00,
        estado: EstadoPago::REGISTRADO,
        registradoPorUserId: $escenario['user']->id,
    );

    $guardado = $repository->guardar($pago);

    expect($guardado->id())->not->toBeNull();
    expect($guardado->monto())->toBeFloat();
    expect($guardado->monto())->toBe(600.00);
    expect($guardado->estado())->toBe(EstadoPago::REGISTRADO);

    expect($repository->listarPorLiquidacion($escenario['liquidacion']->id()))->toHaveCount(1);
});

it('actualiza el estado de un pago', function () {
    $repository = new EloquentPagoProveedorRepository;
    $escenario = crearLiquidacionParaPago();

    $guardado = $repository->guardar(new PagoProveedor(
        id: null,
        liquidacionId: $escenario['liquidacion']->id(),
        fechaPago: new DateTimeImmutable('2026-07-09 16:00:00'),
        monto: 150.00,
        estado: EstadoPago::REGISTRADO,
        registradoPorUserId: $escenario['user']->id,
    ));

    $actualizado = $repository->actualizar(new PagoProveedor(
        id: $guardado->id(),
        liquidacionId: $escenario['liquidacion']->id(),
        fechaPago: new DateTimeImmutable('2026-07-09 16:00:00'),
        monto: 150.00,
        estado: EstadoPago::ANULADO,
        registradoPorUserId: $escenario['user']->id,
        observacion: 'Pago anulado por error',
    ));

    expect($actualizado->estado())->toBe(EstadoPago::ANULADO);
    expect($actualizado->observacion())->toBe('Pago anulado por error');
});
