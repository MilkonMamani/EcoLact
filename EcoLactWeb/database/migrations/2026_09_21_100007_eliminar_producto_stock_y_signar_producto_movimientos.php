<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina la tabla `producto_stock` (saldo cacheado y duplicado): el stock de
 * un producto terminado pasa a ser SIEMPRE la suma con signo de
 * `producto_movimientos`, una sola fuente de verdad.
 *
 * Para poder calcular esa suma, cada movimiento debe indicar su dirección.
 * Antes el tipo AJUSTE guardaba el valor absoluto y perdía el signo, así que
 * se reemplaza por AJUSTE_ENTRADA / AJUSTE_SALIDA.
 *
 * Los AJUSTE históricos no conservan su signo original. Para no perder el
 * saldo real, antes de borrar la tabla se CONCILIA cada producto: si la suma
 * de sus movimientos difiere de `producto_stock.cantidad_actual`, se registra
 * un movimiento AJUSTE por la diferencia con una observación explícita. Así el
 * saldo final coincide exactamente con el que tenía el sistema y queda
 * trazabilidad de la conciliación.
 */
return new class extends Migration
{
    private const EPSILON = 0.005;

    public function up(): void
    {
        DB::table('producto_movimientos')->where('tipo', 'AJUSTE')->update(['tipo' => 'AJUSTE_ENTRADA']);

        if (Schema::hasTable('producto_stock')) {
            $actorId = DB::table('users')
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->where('roles.nombre', 'ADMINISTRADOR')
                ->orderBy('users.id')
                ->value('users.id') ?? DB::table('users')->min('id');

            foreach (DB::table('producto_stock')->get() as $stock) {
                $calculado = $this->saldoPorMovimientos((int) $stock->producto_id);
                $diferencia = round((float) $stock->cantidad_actual - $calculado, 2);

                if (abs($diferencia) < self::EPSILON || $actorId === null) {
                    continue;
                }

                DB::table('producto_movimientos')->insert([
                    'producto_id' => $stock->producto_id,
                    'tipo' => $diferencia > 0 ? 'AJUSTE_ENTRADA' : 'AJUSTE_SALIDA',
                    'cantidad' => abs($diferencia),
                    'lote_produccion_id' => null,
                    'venta_producto_id' => null,
                    'fecha' => now(),
                    'observacion' => 'Conciliación de migración: iguala el historial con el stock que tenía registrado el producto.',
                    'registrado_por_user_id' => $actorId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::drop('producto_stock');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('producto_stock')) {
            Schema::create('producto_stock', function (Blueprint $table) {
                $table->engine('InnoDB');
                $table->charset('utf8mb4');
                $table->collation('utf8mb4_unicode_ci');

                $table->id();
                $table->unsignedBigInteger('producto_id');
                $table->decimal('cantidad_actual', 12, 2)->default(0);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->unique('producto_id', 'uq_producto_stock_producto_id');

                $table->foreign('producto_id', 'fk_producto_stock_producto_id')
                    ->references('id')->on('productos')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            });
        }

        foreach (DB::table('producto_movimientos')->distinct()->pluck('producto_id') as $productoId) {
            DB::table('producto_stock')->insert([
                'producto_id' => $productoId,
                'cantidad_actual' => max($this->saldoPorMovimientos((int) $productoId), 0),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('producto_movimientos')->whereIn('tipo', ['AJUSTE_ENTRADA', 'AJUSTE_SALIDA'])->update(['tipo' => 'AJUSTE']);
    }

    private function saldoPorMovimientos(int $productoId): float
    {
        $entradas = (float) DB::table('producto_movimientos')
            ->where('producto_id', $productoId)
            ->whereIn('tipo', ['INGRESO_PRODUCCION', 'AJUSTE_ENTRADA'])
            ->sum('cantidad');
        $salidas = (float) DB::table('producto_movimientos')
            ->where('producto_id', $productoId)
            ->whereIn('tipo', ['VENTA', 'AJUSTE_SALIDA'])
            ->sum('cantidad');

        return round($entradas - $salidas, 2);
    }
};
