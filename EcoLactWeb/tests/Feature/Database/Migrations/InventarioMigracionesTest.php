<?php

use App\Domain\Produccion\ProductoStockRepository;
use App\Infrastructure\Persistence\Models\InsumoModel;
use App\Infrastructure\Persistence\Models\InsumoMovimientoModel;
use App\Infrastructure\Persistence\Models\LoteProduccionModel;
use App\Infrastructure\Persistence\Models\RecetaDetalleModel;
use App\Infrastructure\Persistence\Models\RecetaModel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

require_once __DIR__.'/../../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/**
 * RefreshDatabase ya aplicó todas las migraciones sobre una base vacía (primer
 * paso = no-op). Estos tests simulan una base con DATOS HEREDADOS (como la de
 * desarrollo/producción antes del cambio) y vuelven a ejecutar las migraciones
 * de datos sobre ese estado.
 */
function migracion(string $archivo): object
{
    return require database_path('migrations/'.$archivo.'.php');
}

function saldoInsumoPorMovimientos(int $insumoId): float
{
    return round((float) InsumoMovimientoModel::where('insumo_id', $insumoId)->get()
        ->sum(fn ($m) => $m->tipo === 'ENTRADA' ? (float) $m->cantidad : -(float) $m->cantidad), 4);
}

it('la migración deja la leche como un único insumo del sistema y elimina las tablas obsoletas', function () {
    expect(InsumoModel::where('codigo', 'LECHE')->count())->toBe(1);
    expect(Schema::hasTable('producto_stock'))->toBeFalse();
    expect(Schema::hasTable('producto_insumos'))->toBeFalse();
    expect(Schema::hasTable('lote_produccion_entregas'))->toBeFalse();
    expect(Schema::hasTable('recetas'))->toBeTrue();
    expect(Schema::hasTable('receta_detalles'))->toBeTrue();
    expect(Schema::hasColumn('insumos', 'unidad_base'))->toBeTrue();
    expect(Schema::hasColumn('insumos', 'unidad_medida'))->toBeFalse();
});

it('reconstruye el saldo de leche desde entregas y lotes históricos con las mismas reglas del sistema anterior', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $proveedor = fixture_proveedor($zona);
    $acopiador = fixture_acopiador($zona);

    // Estado "heredado": la columna manual de litros y sin movimientos de leche.
    Schema::table('lotes_produccion', fn (Blueprint $t) => $t->decimal('litros_leche_utilizados', 12, 2)->default(0));
    InsumoMovimientoModel::where('insumo_id', fixture_leche()->id)->delete();
    InsumoModel::where('codigo', 'LECHE')->update(['stock_actual' => 0]);

    $ok1 = fixture_entrega($proveedor, $acopiador, 30.0, new DateTimeImmutable('2026-09-01 08:00'));
    $ok2 = fixture_entrega($proveedor, $acopiador, 20.0, new DateTimeImmutable('2026-09-02 08:00'));
    $anulada = fixture_entrega($proveedor, $acopiador, 99.0, new DateTimeImmutable('2026-09-03 08:00'));
    $anulada->update(['estado' => 'ANULADA']);
    $rechazada = fixture_entrega($proveedor, $acopiador, 77.0, new DateTimeImmutable('2026-09-04 08:00'));
    fixture_analisis_confirmado($rechazada, 'RECHAZADA', $admin->id);

    $lote = LoteProduccionModel::create([
        'codigo' => 'LOT-HIST', 'fecha_hora_inicio' => '2026-09-02 12:00:00', 'estado' => 'FINALIZADO',
        'registrado_por_user_id' => $admin->id,
    ]);
    DB::table('lotes_produccion')->where('id', $lote->id)->update(['litros_leche_utilizados' => 40]);

    // Otro insumo con stock guardado pero sin movimientos que lo respalden.
    $sal = fixture_insumo('Sal', 'kg', 0.0, 0.0, $admin->id);
    InsumoModel::where('id', $sal->id())->update(['stock_actual' => 50]);

    migracion('2026_09_21_100004_registrar_leche_como_insumo_y_reconstruir_saldo')->up();

    // 30 + 20 recibidos (anulada y rechazada no cuentan) - 40 usados = 10 L.
    expect((float) fixture_leche()->fresh()->stock_actual)->toBe(10.0);
    expect(saldoInsumoPorMovimientos(fixture_leche()->id))->toBe(10.0);

    $ingresos = InsumoMovimientoModel::where('insumo_id', fixture_leche()->id)->where('motivo', 'INGRESO_ACOPIO')->pluck('origen_id')->sort()->values()->all();
    expect($ingresos)->toBe([$ok1->id, $ok2->id]);

    $this->assertDatabaseHas('lote_produccion_insumos', [
        'lote_produccion_id' => $lote->id, 'insumo_id' => fixture_leche()->id, 'cantidad_usada' => 40, 'unidad_base' => 'L',
    ]);
    $this->assertDatabaseHas('insumo_movimientos', [
        'insumo_id' => fixture_leche()->id, 'motivo' => 'CONSUMO_PRODUCCION', 'lote_produccion_id' => $lote->id,
    ]);

    // El stock guardado de "Sal" queda respaldado por un movimiento de saldo inicial.
    expect(saldoInsumoPorMovimientos($sal->id()))->toBe(50.0);
    $this->assertDatabaseHas('insumo_movimientos', ['insumo_id' => $sal->id(), 'motivo' => 'AJUSTE_ENTRADA']);
});

it('es idempotente: ejecutarla otra vez no duplica movimientos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    fixture_stock_leche(10.0, $admin->id);
    $antes = InsumoMovimientoModel::count();

    migracion('2026_09_21_100004_registrar_leche_como_insumo_y_reconstruir_saldo')->up();

    expect(InsumoMovimientoModel::count())->toBe($antes);
});

it('convierte la receta antigua por unidad en una receta v1 INACTIVA sin inventar la leche', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $cuajo = fixture_insumo('Coajo', 'ml', 800.0, 0.0, $admin->id);
    $producto = fixture_producto('queso amarillo', 'Kl');

    Schema::create('producto_insumos', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('producto_id');
        $t->unsignedBigInteger('insumo_id');
        $t->decimal('cantidad_por_unidad', 12, 4);
        $t->timestamps();
    });
    DB::table('producto_insumos')->insert(['producto_id' => $producto->id, 'insumo_id' => $cuajo->id(), 'cantidad_por_unidad' => 2.5]);

    migracion('2026_09_21_100005_backfill_recetas_desde_producto_insumos')->up();

    $receta = RecetaModel::where('producto_id', $producto->id)->firstOrFail();
    expect($receta->estado)->toBe('INACTIVA');
    expect($receta->producto_activo_id)->toBeNull();
    expect($receta->version)->toBe(1);
    expect((float) $receta->rendimiento_cantidad)->toBe(1.0);
    expect($receta->rendimiento_unidad)->toBe('Kl');
    expect($receta->descripcion)->toContain('no incluía la leche');

    $detalle = RecetaDetalleModel::where('receta_id', $receta->id)->firstOrFail();
    expect((float) $detalle->cantidad)->toBe(2.5);
    expect($detalle->unidad)->toBe('ml');
    expect($detalle->insumo_id)->toBe($cuajo->id());
    // La leche NO se inventa dentro de la receta migrada.
    expect(RecetaDetalleModel::where('receta_id', $receta->id)->where('insumo_id', fixture_leche()->id)->exists())->toBeFalse();

    // Idempotente: no crea una segunda receta.
    migracion('2026_09_21_100005_backfill_recetas_desde_producto_insumos')->up();
    expect(RecetaModel::where('producto_id', $producto->id)->count())->toBe(1);
});

it('no elimina producto_insumos si algún producto quedó sin receta equivalente', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $cuajo = fixture_insumo('Cuajo', 'ml', 5.0, 0.0, $admin->id);
    $producto = fixture_producto('Sin migrar');

    Schema::create('producto_insumos', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('producto_id');
        $t->unsignedBigInteger('insumo_id');
        $t->decimal('cantidad_por_unidad', 12, 4);
        $t->timestamps();
    });
    DB::table('producto_insumos')->insert(['producto_id' => $producto->id, 'insumo_id' => $cuajo->id(), 'cantidad_por_unidad' => 1]);

    migracion('2026_09_21_100008_drop_producto_insumos_table')->up();
})->throws(RuntimeException::class, 'sin receta equivalente');

it('no elimina lote_produccion_entregas si tiene historial', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $entrega = fixture_entrega(fixture_proveedor($zona), fixture_acopiador($zona), 10.0);
    $lote = LoteProduccionModel::create([
        'codigo' => 'LOT-X', 'fecha_hora_inicio' => now(), 'estado' => 'EN_PROCESO', 'registrado_por_user_id' => $admin->id,
    ]);

    Schema::create('lote_produccion_entregas', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('lote_produccion_id');
        $t->unsignedBigInteger('entrega_id');
        $t->decimal('litros_utilizados', 10, 2);
        $t->timestamps();
    });
    DB::table('lote_produccion_entregas')->insert(['lote_produccion_id' => $lote->id, 'entrega_id' => $entrega->id, 'litros_utilizados' => 5]);

    migracion('2026_09_21_100009_drop_lote_produccion_entregas_table')->up();
})->throws(RuntimeException::class, 'no se elimina');

it('concilia producto_stock con los movimientos antes de eliminarlo y firma los ajustes históricos', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $producto = fixture_producto('Queso');
    fixture_ingreso_producto($producto->id, 10.0, $admin->id);
    DB::table('producto_movimientos')->insert([
        'producto_id' => $producto->id, 'tipo' => 'AJUSTE', 'cantidad' => 5, 'fecha' => now(),
        'registrado_por_user_id' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    Schema::create('producto_stock', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('producto_id');
        $t->decimal('cantidad_actual', 12, 2)->default(0);
        $t->timestamps();
    });
    // El sistema anterior tenía 8 (el AJUSTE histórico había sido de -2 pero perdió su signo).
    DB::table('producto_stock')->insert(['producto_id' => $producto->id, 'cantidad_actual' => 8]);

    migracion('2026_09_21_100007_eliminar_producto_stock_y_signar_producto_movimientos')->up();

    expect(Schema::hasTable('producto_stock'))->toBeFalse();
    expect(DB::table('producto_movimientos')->where('tipo', 'AJUSTE')->count())->toBe(0);

    $saldo = app(ProductoStockRepository::class)->buscarPorProductoId($producto->id)->cantidadActual();
    expect($saldo)->toBe(8.0);
    $this->assertDatabaseHas('producto_movimientos', ['producto_id' => $producto->id, 'tipo' => 'AJUSTE_SALIDA', 'cantidad' => 7]);
});

it('los cambios de estructura se pueden revertir y volver a aplicar', function () {
    $migraciones = [
        '2026_09_21_100009_drop_lote_produccion_entregas_table',
        '2026_09_21_100008_drop_producto_insumos_table',
        '2026_09_21_100007_eliminar_producto_stock_y_signar_producto_movimientos',
    ];

    foreach ($migraciones as $archivo) {
        migracion($archivo)->down();
    }

    expect(Schema::hasTable('producto_stock'))->toBeTrue();
    expect(Schema::hasTable('producto_insumos'))->toBeTrue();
    expect(Schema::hasTable('lote_produccion_entregas'))->toBeTrue();

    foreach (array_reverse($migraciones) as $archivo) {
        migracion($archivo)->up();
    }

    expect(Schema::hasTable('producto_stock'))->toBeFalse();
    expect(Schema::hasTable('producto_insumos'))->toBeFalse();
    expect(Schema::hasTable('lote_produccion_entregas'))->toBeFalse();
});
