<?php

use App\Application\Produccion\CalcularRequerimientosProduccion;
use App\Application\Produccion\FinalizarProduccion;
use App\Application\Produccion\IniciarPlanificacionProduccion;
use App\Application\Produccion\ObtenerDetalleLoteProduccion;
use App\Application\Produccion\ProduccionBloqueadaException;
use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Produccion\InsumoMovimientoRepository;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use App\Infrastructure\Persistence\Models\LoteProduccionInsumoModel;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use App\Infrastructure\Persistence\Models\NotificacionModel;
use App\Infrastructure\Persistence\Models\PlanificacionProduccionModel;
use App\Infrastructure\Persistence\Models\ProduccionDetalleModel;
use App\Infrastructure\Persistence\Models\ProductoMovimientoModel;
use App\Infrastructure\Persistence\Repositories\EloquentInsumoMovimientoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function saldoInsumo(int $insumoId): float
{
    return (float) InsumoModel::find($insumoId)->stock_actual;
}

/**
 * Queso Brie: 4 L leche + 2 ml cuajo + 10 g sal (base kg) = 1 unidad.
 * Queso fresco: 2 L leche + 1 ml cuajo = 1 unidad.
 * Mozzarella: 3 L leche + 15 g sal (base kg) = 1 unidad.
 */
function escenarioPlanificacion(int $userId, float $leche = 200.0): array
{
    fixture_stock_leche($leche, $userId);
    $cuajo = fixture_insumo('Cuajo', 'ml', 100.0, 0.0, $userId);
    $sal = fixture_insumo('Sal', 'kg', 1.0, 0.0, $userId);
    $brie = fixture_producto('Queso Brie');
    $fresco = fixture_producto('Queso fresco');
    $mozzarella = fixture_producto('Mozzarella');

    fixture_receta($brie->id, [
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 4.0, 'unidad' => 'L'],
        ['insumo_id' => $cuajo->id(), 'cantidad' => 2.0, 'unidad' => 'ml'],
        ['insumo_id' => $sal->id(), 'cantidad' => 10.0, 'unidad' => 'g'],
    ], $userId);
    fixture_receta($fresco->id, [
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 2.0, 'unidad' => 'L'],
        ['insumo_id' => $cuajo->id(), 'cantidad' => 1.0, 'unidad' => 'ml'],
    ], $userId);
    fixture_receta($mozzarella->id, [
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 3.0, 'unidad' => 'L'],
        ['insumo_id' => $sal->id(), 'cantidad' => 15.0, 'unidad' => 'g'],
    ], $userId);

    return [$brie, $fresco, $mozzarella, $cuajo, $sal];
}

it('suma los insumos de varios productos convirtiendo g a kg y conserva la porción de cada producto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$brie, $fresco, $mozzarella, $cuajo, $sal] = escenarioPlanificacion($admin->id);

    $req = app(CalcularRequerimientosProduccion::class)->execute([
        ['producto_id' => $brie->id, 'cantidad' => 5],
        ['producto_id' => $fresco->id, 'cantidad' => 10],
        ['producto_id' => $mozzarella->id, 'cantidad' => 8],
    ]);

    $total = collect($req->insumos)->keyBy('insumoId');
    // Leche: 4x5 + 2x10 + 3x8 = 64 L. Sal: (10 g x 5 + 15 g x 8) = 170 g = 0.17 kg.
    expect($total[fixture_leche()->id]->necesario)->toBe(64.0);
    expect($total[$cuajo->id()]->necesario)->toBe(20.0);
    expect($total[$sal->id()]->necesario)->toBe(0.17);

    $mozzarellaLinea = collect($req->lineas)->firstWhere('productoId', $mozzarella->id);
    expect(collect($mozzarellaLinea['insumos'])->pluck('necesario', 'insumoId')->all())
        ->toEqual([fixture_leche()->id => 24.0, $sal->id() => 0.12]);
});

it('crea un lote independiente por producto, agrupados en una planificación, y descuenta solo lo de cada uno', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$brie, $fresco, $mozzarella, $cuajo, $sal] = escenarioPlanificacion($admin->id);

    $iniciada = app(IniciarPlanificacionProduccion::class)->execute([
        ['producto_id' => $brie->id, 'cantidad' => 5],
        ['producto_id' => $fresco->id, 'cantidad' => 10],
        ['producto_id' => $mozzarella->id, 'cantidad' => 8],
    ], $admin->id, 'Turno mañana');

    expect($iniciada->lotes)->toHaveCount(3);
    expect($iniciada->planificacion?->codigo())->toBe(sprintf('PLN-%05d', $iniciada->planificacion->id()));
    expect(LoteProduccionModel::where('planificacion_produccion_id', $iniciada->planificacion->id())->count())->toBe(3);

    // Cada lote tiene un solo producto con su receta.
    foreach ($iniciada->lotes as $lote) {
        expect(ProduccionDetalleModel::where('lote_produccion_id', $lote->id())->whereNotNull('receta_id')->count())->toBe(1);
        expect($lote->estado())->toBe('EN_PROCESO');
        expect($lote->observacion())->toBe('Turno mañana');
    }

    $loteMozzarella = $iniciada->lotes[2];
    expect(LoteProduccionInsumoModel::where('lote_produccion_id', $loteMozzarella->id())->pluck('cantidad_usada', 'insumo_id')->map(fn ($v) => (float) $v)->all())
        ->toEqual([fixture_leche()->id => 24.0, $sal->id() => 0.12]);

    // Un movimiento CONSUMO_PRODUCCION por insumo y lote (Brie 3 + fresco 2 + Mozzarella 2), nunca duplicado.
    expect(InsumoMovimientoModel::where('motivo', 'CONSUMO_PRODUCCION')->count())->toBe(7);
    expect(saldoInsumo(fixture_leche()->id))->toBe(136.0);
    expect(saldoInsumo($cuajo->id()))->toBe(80.0);
    expect(saldoInsumo($sal->id()))->toBe(0.83);
    expect($iniciada->mensaje())->toContain('3 lotes');
});

it('un solo producto crea un lote sin planificación, como antes', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$brie] = escenarioPlanificacion($admin->id);

    $iniciada = app(IniciarPlanificacionProduccion::class)->execute([['producto_id' => $brie->id, 'cantidad' => 5]], $admin->id);

    expect($iniciada->planificacion)->toBeNull();
    expect($iniciada->lotes)->toHaveCount(1);
    expect($iniciada->lotes[0]->planificacionProduccionId())->toBeNull();
    expect(PlanificacionProduccionModel::count())->toBe(0);
});

it('el mismo producto repetido se agrupa en un solo lote con la cantidad sumada', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$brie, $fresco] = escenarioPlanificacion($admin->id);

    $iniciada = app(IniciarPlanificacionProduccion::class)->execute([
        ['producto_id' => $brie->id, 'cantidad' => 2],
        ['producto_id' => $fresco->id, 'cantidad' => 1],
        ['producto_id' => $brie->id, 'cantidad' => 3],
    ], $admin->id);

    expect($iniciada->lotes)->toHaveCount(2);
    expect((float) ProduccionDetalleModel::where('producto_id', $brie->id)->value('cantidad_planificada'))->toBe(5.0);
});

it('si el total no alcanza, no crea ningún lote e indica exactamente qué insumo falta', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    // Cada producto por separado alcanzaría (20 L y 20 L), pero juntos piden 40 L y hay 30 L.
    [$brie, $fresco] = escenarioPlanificacion($admin->id, 30.0);

    expect(fn () => app(IniciarPlanificacionProduccion::class)->execute([
        ['producto_id' => $brie->id, 'cantidad' => 5],
        ['producto_id' => $fresco->id, 'cantidad' => 10],
    ], $admin->id))->toThrow(ProduccionBloqueadaException::class, 'No hay suficiente leche. Se necesitan 40 L y hay 30 L disponibles.');

    expect(LoteProduccionModel::count())->toBe(0);
    expect(PlanificacionProduccionModel::count())->toBe(0);
    expect(saldoInsumo(fixture_leche()->id))->toBe(30.0);
});

it('un producto sin receta activa bloquea toda la planificación', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$brie] = escenarioPlanificacion($admin->id);
    $sinReceta = fixture_producto('Yogurt sin receta');

    expect(fn () => app(IniciarPlanificacionProduccion::class)->execute([
        ['producto_id' => $brie->id, 'cantidad' => 5],
        ['producto_id' => $sinReceta->id, 'cantidad' => 2],
    ], $admin->id))->toThrow(ProduccionBloqueadaException::class, 'Yogurt sin receta: este producto todavía no tiene una receta configurada.');

    expect(LoteProduccionModel::count())->toBe(0);
});

it('hace ROLLBACK de todos los lotes si falla el descuento de un lote posterior', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$brie, $fresco] = escenarioPlanificacion($admin->id);
    $antes = InsumoModel::orderBy('id')->pluck('stock_actual', 'id')->all();

    // Brie descuenta 3 insumos; el 4.º movimiento (primero del Queso fresco) falla.
    $real = new EloquentInsumoMovimientoRepository;
    $this->app->bind(InsumoMovimientoRepository::class, fn () => new class($real) implements InsumoMovimientoRepository
    {
        private int $guardados = 0;

        public function __construct(private EloquentInsumoMovimientoRepository $real) {}

        public function guardar(InsumoMovimiento $movimiento): InsumoMovimiento
        {
            if (++$this->guardados === 4) {
                throw new RuntimeException('Fallo simulado');
            }

            return $this->real->guardar($movimiento);
        }

        public function listarPorInsumo(int $insumoId): array
        {
            return $this->real->listarPorInsumo($insumoId);
        }

        public function listarPorOrigen(string $origenTipo, int $origenId): array
        {
            return $this->real->listarPorOrigen($origenTipo, $origenId);
        }

        public function listar(): array
        {
            return $this->real->listar();
        }

        public function ultimoMovimientoPorInsumo(): array
        {
            return $this->real->ultimoMovimientoPorInsumo();
        }
    });

    expect(fn () => app(IniciarPlanificacionProduccion::class)->execute([
        ['producto_id' => $brie->id, 'cantidad' => 5],
        ['producto_id' => $fresco->id, 'cantidad' => 10],
    ], $admin->id))->toThrow(RuntimeException::class, 'Fallo simulado');

    expect(InsumoModel::orderBy('id')->pluck('stock_actual', 'id')->all())->toEqual($antes);
    expect(LoteProduccionModel::count())->toBe(0);
    expect(PlanificacionProduccionModel::count())->toBe(0);
    expect(ProduccionDetalleModel::count())->toBe(0);
    expect(InsumoMovimientoModel::where('motivo', 'CONSUMO_PRODUCCION')->count())->toBe(0);
});

it('dos planificaciones seguidas no consumen dos veces el mismo stock', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$brie, $fresco] = escenarioPlanificacion($admin->id, 50.0);
    $lineas = [['producto_id' => $brie->id, 'cantidad' => 5], ['producto_id' => $fresco->id, 'cantidad' => 5]];

    app(IniciarPlanificacionProduccion::class)->execute($lineas, $admin->id);

    expect(fn () => app(IniciarPlanificacionProduccion::class)->execute($lineas, $admin->id))
        ->toThrow(ProduccionBloqueadaException::class, 'No hay suficiente leche');

    expect(saldoInsumo(fixture_leche()->id))->toBe(20.0);
    expect(LoteProduccionModel::count())->toBe(2);
});

it('avisa una sola vez del stock bajo aunque varios lotes consuman el mismo insumo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$brie, $fresco, , $cuajo] = escenarioPlanificacion($admin->id);
    InsumoModel::whereKey($cuajo->id())->update(['stock_minimo' => 90.0]);

    app(IniciarPlanificacionProduccion::class)->execute([
        ['producto_id' => $brie->id, 'cantidad' => 1],
        ['producto_id' => $fresco->id, 'cantidad' => 10],
    ], $admin->id);

    expect(NotificacionModel::where('tipo', 'INSUMO_STOCK_BAJO')->where('referencia_id', $cuajo->id())->count())->toBe(1);
});

it('cada lote se finaliza por separado: los demás siguen en proceso y su stock no cambia', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$brie, $fresco] = escenarioPlanificacion($admin->id);

    $iniciada = app(IniciarPlanificacionProduccion::class)->execute([
        ['producto_id' => $brie->id, 'cantidad' => 5],
        ['producto_id' => $fresco->id, 'cantidad' => 10],
    ], $admin->id);
    [$loteBrie, $loteFresco] = $iniciada->lotes;
    $consumosAntes = InsumoMovimientoModel::where('motivo', 'CONSUMO_PRODUCCION')->count();
    $detalleBrie = ProduccionDetalleModel::where('lote_produccion_id', $loteBrie->id())->first();

    $finalizado = app(FinalizarProduccion::class)->execute($loteBrie->id(), [$detalleBrie->id => 4], $admin->id);

    expect($finalizado->planificacionProduccionId())->toBe($iniciada->planificacion->id());
    expect(LoteProduccionModel::find($loteFresco->id())->estado)->toBe('EN_PROCESO');
    expect((float) $detalleBrie->fresh()->merma)->toBe(1.0);
    expect(ProductoMovimientoModel::where('tipo', 'INGRESO_PRODUCCION')->pluck('producto_id')->all())->toBe([$brie->id]);
    // Finalizar no vuelve a descontar insumos.
    expect(InsumoMovimientoModel::where('motivo', 'CONSUMO_PRODUCCION')->count())->toBe($consumosAntes);

    $detalle = app(ObtenerDetalleLoteProduccion::class)->execute($loteFresco->id());
    expect($detalle->planificacion['codigo'])->toBe($iniciada->planificacion->codigo());
    expect(collect($detalle->planificacion['lotes'])->pluck('estado', 'codigo')->all())
        ->toBe([$loteBrie->codigo() => 'FINALIZADO', $loteFresco->codigo() => 'EN_PROCESO']);
});
