<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría final antes de cargar datos reales (docs/database/DATABASE_AUDIT_FINAL.md).
 * Solo agrega; no toca datos de negocio:
 *
 *   - api_idempotencia.user_id pasa a tener FK a users. Es una tabla técnica
 *     (respuestas guardadas para reintentos de la app), así que se borra con
 *     su usuario. Antes de crear la FK se eliminan las filas cuyo usuario ya no
 *     existe y las reservas sin respuesta del modelo anterior del middleware
 *     (la clave se reservaba fuera de la transacción): ninguna corresponde a
 *     una operación registrada.
 *   - ventas_producto(fecha): dashboard del administrador (rango de fechas) e
 *     historiales ordenados por fecha.
 *   - lotes_produccion(estado, fecha_hora_inicio): listado de lotes por estado
 *     (EN_PROCESO / FINALIZADO) ordenado por inicio.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('api_idempotencia')
            ->where(fn ($q) => $q->whereNull('estado_http')
                ->orWhereNotIn('user_id', DB::table('users')->select('id')))
            ->delete();

        Schema::table('api_idempotencia', function (Blueprint $table) {
            $table->foreign('user_id', 'fk_api_idempotencia_user_id')
                ->references('id')->on('users')->cascadeOnUpdate()->cascadeOnDelete();
        });

        if (! Schema::hasIndex('ventas_producto', 'idx_ventas_producto_fecha')) {
            Schema::table('ventas_producto', function (Blueprint $table) {
                $table->index('fecha', 'idx_ventas_producto_fecha');
            });
        }

        if (! Schema::hasIndex('lotes_produccion', 'idx_lotes_produccion_estado_inicio')) {
            Schema::table('lotes_produccion', function (Blueprint $table) {
                $table->index(['estado', 'fecha_hora_inicio'], 'idx_lotes_produccion_estado_inicio');
            });
        }
    }

    public function down(): void
    {
        Schema::table('lotes_produccion', function (Blueprint $table) {
            $table->dropIndex('idx_lotes_produccion_estado_inicio');
        });

        Schema::table('ventas_producto', function (Blueprint $table) {
            $table->dropIndex('idx_ventas_producto_fecha');
        });

        Schema::table('api_idempotencia', function (Blueprint $table) {
            $table->dropForeign('fk_api_idempotencia_user_id');
        });
    }
};
