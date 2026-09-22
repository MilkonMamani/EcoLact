<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acopiadores', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('zona_id');
            $table->char('dni', 8);
            $table->string('nombres', 100);
            $table->string('apellidos', 150);
            $table->string('telefono', 20)->nullable();
            $table->string('estado', 20);
            $table->date('fecha_ingreso')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('user_id', 'uq_acopiadores_user_id');
            $table->unique('dni', 'uq_acopiadores_dni');
            $table->index(['zona_id', 'estado'], 'idx_acopiadores_zona_id_estado');

            $table->foreign('user_id', 'fk_acopiadores_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('zona_id', 'fk_acopiadores_zona_id')
                ->references('id')->on('zonas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acopiadores');
    }
};
