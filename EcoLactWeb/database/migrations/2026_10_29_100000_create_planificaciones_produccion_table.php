<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una PLANIFICACIÓN agrupa los lotes que se iniciaron juntos desde "Nueva
 * producción" (5 Queso Brie + 10 Queso fresco + 8 Mozzarella = 3 lotes).
 * Cada producto conserva su propio lote, consumos y movimientos; la
 * planificación solo dice qué lotes nacieron en la misma operación.
 *
 * `lotes_produccion.planificacion_produccion_id` es nullable: los lotes
 * anteriores (y los que crea la API móvil) quedan sin planificación y siguen
 * funcionando igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planificaciones_produccion', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->string('codigo', 50);
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('registrado_por_user_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('codigo', 'uq_planificaciones_produccion_codigo');

            $table->foreign('registrado_por_user_id', 'fk_planificaciones_produccion_registrado_por_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        Schema::table('lotes_produccion', function (Blueprint $table) {
            $table->unsignedBigInteger('planificacion_produccion_id')->nullable()->after('id');

            $table->foreign('planificacion_produccion_id', 'fk_lotes_produccion_planificacion_produccion_id')
                ->references('id')->on('planificaciones_produccion')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lotes_produccion', function (Blueprint $table) {
            $table->dropForeign('fk_lotes_produccion_planificacion_produccion_id');
            $table->dropColumn('planificacion_produccion_id');
        });

        Schema::dropIfExists('planificaciones_produccion');
    }
};
