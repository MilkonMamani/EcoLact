<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una receta es SOLO configuración: qué insumos (incluida la leche) y cuánto
 * de cada uno se necesita para obtener `rendimiento_cantidad` del producto.
 * Guardarla nunca mueve inventario.
 *
 * Versionado: (producto_id, version) es único. Una versión ya usada por una
 * producción no se edita: se crea la siguiente versión. Como máximo una
 * receta ACTIVA por producto, garantizado por la base de datos con
 * `producto_activo_id` (= producto_id solo mientras la receta está ACTIVA,
 * NULL en las demás; un índice único admite varios NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recetas', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('producto_id');
            $table->string('nombre', 120);
            $table->text('descripcion')->nullable();
            $table->decimal('rendimiento_cantidad', 12, 4);
            $table->string('rendimiento_unidad', 30);
            $table->unsignedInteger('version')->default(1);
            $table->string('estado', 20);
            $table->unsignedBigInteger('producto_activo_id')->nullable();
            $table->unsignedBigInteger('created_by_user_id');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['producto_id', 'version'], 'uq_recetas_producto_id_version');
            $table->unique('producto_activo_id', 'uq_recetas_producto_activo_id');
            $table->index('estado', 'idx_recetas_estado');

            $table->foreign('producto_id', 'fk_recetas_producto_id')
                ->references('id')->on('productos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('created_by_user_id', 'fk_recetas_created_by_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        Schema::create('receta_detalles', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('receta_id');
            $table->unsignedBigInteger('insumo_id');
            $table->decimal('cantidad', 14, 4);
            $table->string('unidad', 10);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['receta_id', 'insumo_id'], 'uq_receta_detalles_receta_id_insumo_id');

            $table->foreign('receta_id', 'fk_receta_detalles_receta_id')
                ->references('id')->on('recetas')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('insumo_id', 'fk_receta_detalles_insumo_id')
                ->references('id')->on('insumos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receta_detalles');
        Schema::dropIfExists('recetas');
    }
};
