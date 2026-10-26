<?php

use App\Application\Produccion\ActualizarInsumo;
use App\Application\Produccion\CalcularCompraInsumos;
use App\Application\Produccion\RegistrarInsumo;
use App\Application\Produccion\RegistrarMovimientoInsumo;
use App\Domain\Shared\Enums\MotivoMovimientoInsumo;
use App\Domain\Shared\Enums\TipoInsumo;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function saldoPorMovimientos(int $insumoId): float
{
    return round((float) InsumoMovimientoModel::where('insumo_id', $insumoId)->get()
        ->sum(fn ($m) => $m->tipo === 'ENTRADA' ? (float) $m->cantidad : -(float) $m->cantidad), 4);
}

it('la leche existe una sola vez como insumo del sistema identificado por código', function () {
    $leche = InsumoModel::where('codigo', 'LECHE')->get();

    expect($leche)->toHaveCount(1);
    expect($leche[0]->tipo)->toBe('MATERIA_PRIMA');
    expect($leche[0]->unidad_base)->toBe('L');
    expect($leche[0]->nombre)->toBe('Leche');
});

it('el stock de un insumo es la suma de sus movimientos (fuente única de verdad)', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $sal = fixture_insumo('Sal', 'kg', 10.0, 0.0, $admin->id);

    app(RegistrarMovimientoInsumo::class)->execute($sal->id(), MotivoMovimientoInsumo::INGRESO_COMPRA, 'ENTRADA', 5.0, $admin->id);
    app(RegistrarMovimientoInsumo::class)->execute($sal->id(), MotivoMovimientoInsumo::AJUSTE_SALIDA, 'SALIDA', 3.0, $admin->id);

    expect((float) InsumoModel::find($sal->id())->stock_actual)->toBe(12.0);
    expect(saldoPorMovimientos($sal->id()))->toBe(12.0);
});

it('convierte la unidad de un movimiento a la unidad base del insumo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $azucar = fixture_insumo('Azúcar', 'g', 0.0, 0.0, $admin->id);

    app(RegistrarMovimientoInsumo::class)->execute($azucar->id(), MotivoMovimientoInsumo::INGRESO_COMPRA, 'ENTRADA', 50.0, $admin->id, unidad: 'kg');

    expect((float) InsumoModel::find($azucar->id())->stock_actual)->toBe(50000.0);
    expect((float) InsumoMovimientoModel::where('insumo_id', $azucar->id())->value('cantidad'))->toBe(50000.0);
});

it('rechaza una unidad incompatible con la del insumo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $azucar = fixture_insumo('Azúcar', 'g', 0.0, 0.0, $admin->id);

    app(RegistrarMovimientoInsumo::class)->execute($azucar->id(), MotivoMovimientoInsumo::INGRESO_COMPRA, 'ENTRADA', 5.0, $admin->id, unidad: 'L');
})->throws(DomainException::class, 'no es compatible');

it('nunca deja el stock negativo y no guarda ningún movimiento cuando se rechaza', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $sal = fixture_insumo('Sal', 'kg', 4.0, 0.0, $admin->id);
    $antes = InsumoMovimientoModel::count();

    expect(fn () => app(RegistrarMovimientoInsumo::class)->execute($sal->id(), MotivoMovimientoInsumo::AJUSTE_SALIDA, 'SALIDA', 5.0, $admin->id))
        ->toThrow(DomainException::class, 'No hay suficiente Sal');

    expect((float) InsumoModel::find($sal->id())->stock_actual)->toBe(4.0);
    expect(InsumoMovimientoModel::count())->toBe($antes);
});

it('un motivo con dirección fija no acepta la dirección contraria', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $sal = fixture_insumo('Sal', 'kg', 4.0, 0.0, $admin->id);

    app(RegistrarMovimientoInsumo::class)->execute($sal->id(), MotivoMovimientoInsumo::CONSUMO_PRODUCCION, 'ENTRADA', 1.0, $admin->id);
})->throws(InvalidArgumentException::class);

it('crear un insumo solo crea su ficha: nace con stock 0 y sin movimientos', function () {
    $cuajo = app(RegistrarInsumo::class)->execute(nombre: 'Cuajo', unidadBase: 'ml', stockMinimo: 100.0);

    expect($cuajo->stockActual())->toBe(0.0);
    expect($cuajo->estado())->toBe('ACTIVO');
    expect(InsumoMovimientoModel::where('insumo_id', $cuajo->id())->count())->toBe(0);
});

it('la leche no se puede comprar, desactivar ni cambiar de tipo, pero sí editar su información no crítica', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $leche = fixture_leche();

    expect(fn () => app(CalcularCompraInsumos::class)->execute([['insumo_id' => $leche->id, 'presentacion_id' => 0, 'cantidad' => 10]]))
        ->toThrow(DomainException::class, 'La leche no se compra');

    expect(fn () => app(ActualizarInsumo::class)->execute($leche->id, 'Leche', null, 0.0, 'INACTIVO'))
        ->toThrow(DomainException::class, 'no se puede desactivar');

    expect(fn () => app(ActualizarInsumo::class)->execute($leche->id, 'Leche', null, 0.0, 'ACTIVO', TipoInsumo::OTRO))
        ->toThrow(DomainException::class, 'no se puede cambiar de tipo');

    $actualizada = app(ActualizarInsumo::class)->execute($leche->id, 'Leche', 'Leche cruda de proveedores', 200.0, 'ACTIVO');
    expect($actualizada->stockMinimo())->toBe(200.0);
    expect($actualizada->codigo())->toBe('LECHE');
    expect($actualizada->unidadBase())->toBe('L');
});

it('no permite un segundo insumo con el mismo nombre', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_insumo('Sal', 'kg', 0.0, 0.0, $admin->id);
    fixture_insumo('Sal', 'kg', 0.0, 0.0, $admin->id);
})->throws(DomainException::class, 'Ya existe un insumo');
