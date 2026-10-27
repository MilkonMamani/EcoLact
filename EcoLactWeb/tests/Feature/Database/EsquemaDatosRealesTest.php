<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

require_once __DIR__.'/../../Support/EcoLactFixtures.php';

uses(TestCase::class, RefreshDatabase::class);

/**
 * Esquema que la aplicación espera antes de cargar datos reales
 * (docs/database/DATABASE_AUDIT_FINAL.md). Se comprueba sobre la base que dejan
 * las migraciones, no sobre lo que dicen los documentos.
 */
function indicesUnicos(string $tabla): array
{
    return collect(Schema::getIndexes($tabla))
        ->filter(fn (array $indice): bool => $indice['unique'] && ! $indice['primary'])
        ->map(fn (array $indice): string => implode(',', $indice['columns']))
        ->values()->all();
}

function clavesForaneas(string $tabla): array
{
    return collect(Schema::getForeignKeys($tabla))
        ->mapWithKeys(fn (array $fk): array => [implode(',', $fk['columns']) => $fk['foreign_table']])
        ->all();
}

it('tiene las tablas de muestreo, idempotencia, compras, caja y asistencia', function (string $tabla) {
    expect(Schema::hasTable($tabla))->toBeTrue();
})->with([
    'muestreos_calidad', 'api_idempotencia', 'presentaciones_insumo', 'compras_insumo',
    'compra_insumo_detalles', 'movimientos_caja', 'trabajadores', 'trabajador_periodos_actividad',
    'jornadas_personal', 'categorias_productos',
]);

it('no conserva tablas eliminadas a propósito', function (string $tabla) {
    expect(Schema::hasTable($tabla))->toBeFalse();
})->with(['producto_stock', 'producto_insumos', 'lote_produccion_entregas']);

it('protege con índices únicos lo que nunca debe duplicarse', function (string $tabla, string $columnas) {
    expect(indicesUnicos($tabla))->toContain($columnas);
})->with([
    'una muestra por entrega' => ['muestreos_calidad', 'entrega_id'],
    'un análisis por entrega' => ['analisis_calidad', 'entrega_id'],
    'reintento de acopio' => ['entregas', 'token_idempotencia'],
    'reintento de venta, compra y producción' => ['api_idempotencia', 'user_id,clave'],
    'un movimiento de caja por operación' => ['movimientos_caja', 'referencia_tipo,referencia_id'],
    'una jornada por trabajador y día' => ['jornadas_personal', 'trabajador_id,fecha'],
    'un trabajador por usuario' => ['trabajadores', 'user_id'],
    'un QR estable por trabajador' => ['trabajadores', 'qr_token'],
    'QR del proveedor aparte' => ['proveedores', 'qr_token'],
    'una fotografía de consumo por insumo y lote' => ['lote_produccion_insumos', 'lote_produccion_id,insumo_id'],
    'un pago por obligación' => ['pagos', 'obligacion_pago_id'],
]);

it('tiene los índices de consulta agregados en la auditoría', function () {
    $nombres = fn (string $tabla): array => array_column(Schema::getIndexes($tabla), 'name');

    expect($nombres('ventas_producto'))->toContain('idx_ventas_producto_fecha');
    expect($nombres('lotes_produccion'))->toContain('idx_lotes_produccion_estado_inicio');
});

it('guarda el umbral mayorista y el contexto de muestreo', function () {
    expect(Schema::hasColumn('producto_precios', 'cantidad_minima'))->toBeTrue();
    expect(Schema::hasColumns('muestreos_calidad', [
        'entrega_id', 'seleccionado_por_user_id', 'metodo_seleccion', 'tipo_control',
        'fecha_seleccion', 'fecha_captura_local', 'estado', 'observacion',
    ]))->toBeTrue();
    expect(Schema::hasColumns('produccion_detalles', ['cantidad_planificada', 'cantidad_obtenida', 'merma', 'receta_id', 'receta_version']))->toBeTrue();
    expect(Schema::hasColumns('ventas_producto', ['precio_unitario', 'importe_total', 'tipo_cliente']))->toBeTrue();
    expect(Schema::hasColumn('movimientos_caja', 'saldo'))->toBeFalse();
});

it('relaciona con claves foráneas las tablas operativas', function (string $tabla, string $columna, string $referencia) {
    expect(clavesForaneas($tabla))->toHaveKey($columna, $referencia);
})->with([
    ['muestreos_calidad', 'entrega_id', 'entregas'],
    ['muestreos_calidad', 'seleccionado_por_user_id', 'users'],
    ['api_idempotencia', 'user_id', 'users'],
    ['jornadas_personal', 'trabajador_id', 'trabajadores'],
    ['trabajadores', 'user_id', 'users'],
    ['compra_insumo_detalles', 'compra_insumo_id', 'compras_insumo'],
    ['compra_insumo_detalles', 'insumo_movimiento_id', 'insumo_movimientos'],
    ['presentaciones_insumo', 'insumo_id', 'insumos'],
    ['ventas_producto', 'producto_id', 'productos'],
    ['ventas_producto', 'comprador_id', 'compradores'],
    ['ventas_producto', 'proveedor_id', 'proveedores'],
    ['producto_movimientos', 'producto_id', 'productos'],
    ['lote_produccion_insumos', 'lote_produccion_id', 'lotes_produccion'],
    ['receta_detalles', 'receta_id', 'recetas'],
    ['movimientos_caja', 'registrado_por_user_id', 'users'],
]);

it('rechaza registros huérfanos', function (string $tabla, Closure $fila) {
    $datos = $fila();
    $error = null;

    try {
        DB::table($tabla)->insert($datos);
    } catch (QueryException $e) {
        $error = $e->getMessage();
    }

    // Debe fallar por la clave foránea, no por otra columna mal puesta.
    expect($error)->not->toBeNull()->and(mb_strtolower((string) $error))->toContain('foreign key');
})->with([
    'venta con producto inexistente' => ['ventas_producto', fn () => [
        'producto_id' => 999999, 'tipo_cliente' => 'PUBLICO', 'cantidad' => 1, 'precio_unitario' => 1,
        'importe_total' => 1, 'forma_pago' => 'CONTADO', 'estado' => 'REGISTRADA', 'fecha' => now(),
        'registrado_por_user_id' => fixture_usuario('ADMINISTRADOR')->id,
    ]],
    'detalle de compra sin compra' => ['compra_insumo_detalles', fn () => [
        'compra_insumo_id' => 999999, 'insumo_id' => fixture_leche()->id, 'presentacion_nombre' => 'Saco',
        'presentacion_cantidad' => 1, 'presentacion_unidad' => 'kg', 'cantidad_presentaciones' => 1,
        'cantidad_base' => 1, 'unidad_base' => 'kg',
    ]],
    'análisis sin entrega' => ['analisis_calidad', fn () => [
        'entrega_id' => 999999, 'estado' => 'BORRADOR', 'fecha_analisis' => now(),
    ]],
    'muestra sin entrega' => ['muestreos_calidad', fn () => [
        'entrega_id' => 999999, 'seleccionado_por_user_id' => fixture_usuario('CALIDAD')->id,
        'metodo_seleccion' => 'MANUAL', 'fecha_seleccion' => now(), 'estado' => 'SELECCIONADA',
    ]],
    'jornada sin trabajador' => ['jornadas_personal', fn () => [
        'trabajador_id' => 999999, 'rol_id' => fixture_usuario('VENTAS')->role_id, 'fecha' => now()->toDateString(),
        'estado' => 'PRESENTE', 'origen' => 'QR',
    ]],
    'detalle de receta sin receta' => ['receta_detalles', fn () => [
        'receta_id' => 999999, 'insumo_id' => fixture_leche()->id, 'cantidad' => 1, 'unidad' => 'L',
    ]],
    'movimiento de producto sin producto' => ['producto_movimientos', fn () => [
        'producto_id' => 999999, 'tipo' => 'AJUSTE', 'cantidad' => 1, 'fecha' => now(),
        'registrado_por_user_id' => fixture_usuario('ADMINISTRADOR')->id,
    ]],
    'clave de idempotencia sin usuario' => ['api_idempotencia', fn () => [
        'user_id' => 999999, 'clave' => 'x', 'ruta' => 'POST api/x',
    ]],
]);

it('no permite dos muestras de la misma entrega aunque se salte la aplicación', function () {
    $admin = fixture_usuario('ADMINISTRADOR');
    $zona = fixture_zona();
    $entrega = fixture_entrega(fixture_proveedor($zona), fixture_acopiador($zona), 50, new DateTimeImmutable);
    $muestra = ['entrega_id' => $entrega->id, 'seleccionado_por_user_id' => $admin->id,
        'metodo_seleccion' => 'MANUAL', 'fecha_seleccion' => now(), 'estado' => 'SELECCIONADA'];

    DB::table('muestreos_calidad')->insert($muestra);

    expect(fn () => DB::table('muestreos_calidad')->insert($muestra))->toThrow(QueryException::class);
});
