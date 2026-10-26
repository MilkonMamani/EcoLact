<?php

use App\Application\Produccion\CalcularRequerimientosProduccion;
use App\Application\Produccion\IniciarProduccion;
use App\Application\Produccion\ObtenerDetalleLoteProduccion;
use App\Application\Produccion\ProduccionBloqueadaException;
use App\Domain\Produccion\InsumoMovimiento;
use App\Domain\Produccion\InsumoMovimientoRepository;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use App\Infrastructure\Persistence\Models\LoteProduccionInsumoModel;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use App\Infrastructure\Persistence\Models\ProduccionDetalleModel;
use App\Infrastructure\Persistence\Models\RecetaModel;
use App\Infrastructure\Persistence\Repositories\EloquentInsumoMovimientoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

function stockDe(int $insumoId): float
{
    return (float) InsumoModel::find($insumoId)->stock_actual;
}

/**
 * Queso Paria: 10 L leche + 5 ml cuajo + 20 g sal = 1 unidad.
 * Yogur Fresa: 1 L leche + 30 g azúcar (base kg) = 1 unidad.
 */
function escenarioProduccion(int $userId): array
{
    fixture_stock_leche(1500.0, $userId);
    $cuajo = fixture_insumo('Cuajo', 'ml', 800.0, 0.0, $userId);
    $sal = fixture_insumo('Sal', 'g', 5000.0, 0.0, $userId);
    $azucar = fixture_insumo('Azúcar', 'kg', 6.0, 0.0, $userId);
    $queso = fixture_producto('Queso Paria 1 kg');
    $yogur = fixture_producto('Yogurt Fresa 1 L');

    fixture_receta($queso->id, [
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 10.0, 'unidad' => 'L'],
        ['insumo_id' => $cuajo->id(), 'cantidad' => 5.0, 'unidad' => 'ml'],
        ['insumo_id' => $sal->id(), 'cantidad' => 20.0, 'unidad' => 'g'],
    ], $userId);
    fixture_receta($yogur->id, [
        ['insumo_id' => fixture_leche()->id, 'cantidad' => 1.0, 'unidad' => 'L'],
        ['insumo_id' => $azucar->id(), 'cantidad' => 30.0, 'unidad' => 'g'],
    ], $userId);

    return [$queso, $yogur, $cuajo, $sal, $azucar];
}

it('multiplica la receta por la cantidad a producir y suma los ingredientes repetidos entre productos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso, $yogur, $cuajo, $sal, $azucar] = escenarioProduccion($admin->id);

    $req = app(CalcularRequerimientosProduccion::class)->execute([
        ['producto_id' => $queso->id, 'cantidad' => 100],
        ['producto_id' => $yogur->id, 'cantidad' => 50],
    ]);

    $porInsumo = collect($req->insumos)->keyBy('insumoId');

    // Leche: 10 x 100 + 1 x 50 = 1050 L (un solo total para los dos productos).
    expect($porInsumo[fixture_leche()->id]->necesario)->toBe(1050.0);
    expect($porInsumo[fixture_leche()->id]->disponible)->toBe(1500.0);
    expect($porInsumo[$cuajo->id()]->necesario)->toBe(500.0);
    // Sal: 20 g x 100 = 2000 g (la base es g).
    expect($porInsumo[$sal->id()]->necesario)->toBe(2000.0);
    // Azúcar: la receta está en g y el inventario en kg: 30 g x 50 = 1500 g = 1.5 kg.
    expect($porInsumo[$azucar->id()]->necesario)->toBe(1.5);
    expect($porInsumo[$azucar->id()]->unidadBase)->toBe('kg');
    expect($req->puedeIniciar())->toBeTrue();
});

it('previsualizar los requerimientos no descuenta nada del inventario', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso] = escenarioProduccion($admin->id);
    $antes = InsumoModel::orderBy('id')->pluck('stock_actual', 'id')->all();
    $movimientos = InsumoMovimientoModel::count();

    app(CalcularRequerimientosProduccion::class)->execute([['producto_id' => $queso->id, 'cantidad' => 100]]);

    expect(InsumoModel::orderBy('id')->pluck('stock_actual', 'id')->all())->toEqual($antes);
    expect(InsumoMovimientoModel::count())->toBe($movimientos);
    expect(LoteProduccionModel::count())->toBe(0);
});

it('suma las líneas repetidas del mismo producto', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso] = escenarioProduccion($admin->id);

    $req = app(CalcularRequerimientosProduccion::class)->execute([
        ['producto_id' => $queso->id, 'cantidad' => 30],
        ['producto_id' => $queso->id, 'cantidad' => 20],
    ]);

    expect($req->lineas)->toHaveCount(1);
    expect($req->lineas[0]['cantidad'])->toBe(50.0);
});

it('iniciar producción descuenta EXACTAMENTE receta x cantidad, crea el lote EN_PROCESO y guarda la fotografía del consumo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso, $yogur, $cuajo, $sal, $azucar] = escenarioProduccion($admin->id);

    $lote = app(IniciarProduccion::class)->execute([
        ['producto_id' => $queso->id, 'cantidad' => 100],
        ['producto_id' => $yogur->id, 'cantidad' => 50],
    ], $admin->id, 'Turno mañana');

    expect($lote->codigo())->toBe(sprintf('LOT-%05d', $lote->id()));
    expect($lote->estado())->toBe('EN_PROCESO');
    expect($lote->fechaHoraFin())->toBeNull();

    expect(stockDe(fixture_leche()->id))->toBe(450.0);
    expect(stockDe($cuajo->id()))->toBe(300.0);
    expect(stockDe($sal->id()))->toBe(3000.0);
    expect(stockDe($azucar->id()))->toBe(4.5);

    $consumos = LoteProduccionInsumoModel::where('lote_produccion_id', $lote->id())->get()->keyBy('insumo_id');
    expect((float) $consumos[fixture_leche()->id]->cantidad_usada)->toBe(1050.0);
    expect((float) $consumos[fixture_leche()->id]->cantidad_requerida)->toBe(1050.0);
    expect($consumos[fixture_leche()->id]->unidad_base)->toBe('L');
    expect((float) $consumos[$azucar->id()]->cantidad_usada)->toBe(1.5);

    $detalles = ProduccionDetalleModel::where('lote_produccion_id', $lote->id())->get();
    expect($detalles)->toHaveCount(2);
    expect($detalles->every(fn ($d) => $d->cantidad_obtenida === null && $d->receta_id !== null && $d->receta_version === 1))->toBeTrue();

    $movimientos = InsumoMovimientoModel::where('lote_produccion_id', $lote->id())->get();
    expect($movimientos)->toHaveCount(4);
    expect($movimientos->every(fn ($m) => $m->motivo === 'CONSUMO_PRODUCCION' && $m->tipo === 'SALIDA' && $m->origen_tipo === 'LOTE_PRODUCCION'))->toBeTrue();
});

it('iniciar producción no ingresa producto terminado al inventario todavía', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso] = escenarioProduccion($admin->id);

    app(IniciarProduccion::class)->execute([['producto_id' => $queso->id, 'cantidad' => 10]], $admin->id);

    $this->assertDatabaseCount('producto_movimientos', 0);
});

it('bloquea con un mensaje entendible cuando falta stock y muestra necesario, disponible y faltante', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [, $yogur, , , $azucar] = escenarioProduccion($admin->id);

    // 300 yogures x 30 g = 9 kg de azúcar, hay 6.
    try {
        app(IniciarProduccion::class)->execute([['producto_id' => $yogur->id, 'cantidad' => 300]], $admin->id);
        $this->fail('Debió bloquearse.');
    } catch (ProduccionBloqueadaException $e) {
        expect($e->getMessage())->toContain('No hay suficiente Azúcar. Se necesitan 9 kg y hay 6 kg disponibles.');
    }

    expect(stockDe($azucar->id()))->toBe(6.0);
    expect(LoteProduccionModel::count())->toBe(0);
});

it('un producto sin receta activa no se puede producir', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    escenarioProduccion($admin->id);
    $sinReceta = fixture_producto('Yogurt Durazno 1 L');

    try {
        app(IniciarProduccion::class)->execute([['producto_id' => $sinReceta->id, 'cantidad' => 5]], $admin->id);
        $this->fail('Debió bloquearse.');
    } catch (ProduccionBloqueadaException $e) {
        expect($e->getMessage())->toContain('todavía no tiene una receta configurada');
    }

    expect(LoteProduccionModel::count())->toBe(0);
});

it('una receta desactivada deja el producto sin receta activa', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso] = escenarioProduccion($admin->id);
    RecetaModel::where('producto_id', $queso->id)->update(['estado' => 'INACTIVA', 'producto_activo_id' => null]);

    $req = app(CalcularRequerimientosProduccion::class)->execute([['producto_id' => $queso->id, 'cantidad' => 1]]);

    expect($req->productosSinReceta)->toHaveCount(1);
    expect($req->puedeIniciar())->toBeFalse();
});

it('hace ROLLBACK completo si algo falla a mitad de la producción', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso, , $cuajo] = escenarioProduccion($admin->id);
    $antes = InsumoModel::orderBy('id')->pluck('stock_actual', 'id')->all();

    // Fuerza un fallo real DESPUÉS de haber descontado el primer insumo: el
    // segundo movimiento lanza una excepción al guardarse.
    $real = new EloquentInsumoMovimientoRepository;
    $this->app->bind(InsumoMovimientoRepository::class, fn () => new class($real) implements InsumoMovimientoRepository
    {
        private int $guardados = 0;

        public function __construct(private EloquentInsumoMovimientoRepository $real) {}

        public function guardar(InsumoMovimiento $movimiento): InsumoMovimiento
        {
            if (++$this->guardados === 2) {
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
    });

    expect(fn () => app(IniciarProduccion::class)->execute([['producto_id' => $queso->id, 'cantidad' => 10]], $admin->id))
        ->toThrow(RuntimeException::class, 'Fallo simulado');

    expect(InsumoModel::orderBy('id')->pluck('stock_actual', 'id')->all())->toEqual($antes);
    expect(LoteProduccionModel::count())->toBe(0);
    expect(ProduccionDetalleModel::count())->toBe(0);
    expect(LoteProduccionInsumoModel::count())->toBe(0);
    expect(InsumoMovimientoModel::where('motivo', 'CONSUMO_PRODUCCION')->count())->toBe(0);
});

it('dos producciones seguidas no pueden usar dos veces el mismo stock (se vuelve a leer el saldo)', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_stock_leche(100.0, $admin->id);
    $queso = fixture_producto('Queso');
    fixture_receta($queso->id, [['insumo_id' => fixture_leche()->id, 'cantidad' => 10.0, 'unidad' => 'L']], $admin->id);

    // Ambos usuarios vieron 100 L en pantalla y piden 80 L cada uno.
    app(IniciarProduccion::class)->execute([['producto_id' => $queso->id, 'cantidad' => 8]], $admin->id);

    expect(fn () => app(IniciarProduccion::class)->execute([['producto_id' => $queso->id, 'cantidad' => 8]], $admin->id))
        ->toThrow(ProduccionBloqueadaException::class, 'No hay suficiente leche');

    expect(stockDe(fixture_leche()->id))->toBe(20.0);
    expect(LoteProduccionModel::count())->toBe(1);
});

it('el stock nunca queda negativo aunque se produzca hasta agotarlo', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_stock_leche(100.0, $admin->id);
    $queso = fixture_producto('Queso');
    fixture_receta($queso->id, [['insumo_id' => fixture_leche()->id, 'cantidad' => 10.0, 'unidad' => 'L']], $admin->id);

    app(IniciarProduccion::class)->execute([['producto_id' => $queso->id, 'cantidad' => 10]], $admin->id);

    expect(stockDe(fixture_leche()->id))->toBe(0.0);
});

it('cambiar la receta después no altera lo que consumió un lote histórico', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso] = escenarioProduccion($admin->id);
    $lote = app(IniciarProduccion::class)->execute([['producto_id' => $queso->id, 'cantidad' => 10]], $admin->id);

    $receta = RecetaModel::where('producto_id', $queso->id)->first();
    $receta->detalles()->update(['cantidad' => 999]);

    $consumoLeche = LoteProduccionInsumoModel::where('lote_produccion_id', $lote->id())->where('insumo_id', fixture_leche()->id)->first();
    expect((float) $consumoLeche->cantidad_usada)->toBe(100.0);
});

it('la leche utilizada de un lote se deriva del consumo real y coincide con los movimientos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso] = escenarioProduccion($admin->id);
    $lote = app(IniciarProduccion::class)->execute([['producto_id' => $queso->id, 'cantidad' => 12]], $admin->id);

    $detalle = app(ObtenerDetalleLoteProduccion::class)->execute($lote->id());
    $movimientos = app(InsumoMovimientoRepository::class)->listarPorOrigen('LOTE_PRODUCCION', $lote->id());
    $lecheMovida = collect($movimientos)->first(fn ($m) => $m->insumoId() === fixture_leche()->id)->cantidad();

    expect($detalle->lecheUtilizada)->toBe(120.0);
    expect($lecheMovida)->toBe(120.0);
    expect(Schema::hasColumn('lotes_produccion', 'litros_leche_utilizados'))->toBeFalse();
});

it('rechaza cantidades no positivas y listas vacías', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso] = escenarioProduccion($admin->id);

    expect(fn () => app(IniciarProduccion::class)->execute([], $admin->id))->toThrow(DomainException::class, 'al menos un producto');
    expect(fn () => app(IniciarProduccion::class)->execute([['producto_id' => $queso->id, 'cantidad' => 0]], $admin->id))->toThrow(DomainException::class, 'mayor que cero');
});

it('un producto inactivo no se puede producir', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    [$queso] = escenarioProduccion($admin->id);
    $queso->update(['estado' => 'INACTIVO']);

    app(IniciarProduccion::class)->execute([['producto_id' => $queso->id, 'cantidad' => 1]], $admin->id);
})->throws(DomainException::class, 'inactivo');
