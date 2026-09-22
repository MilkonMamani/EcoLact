<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personal_ventas', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->char('dni', 8);
            $table->string('nombres', 100);
            $table->string('apellidos', 150);
            $table->string('telefono', 20)->nullable();
            $table->string('estado', 20);
            $table->date('fecha_ingreso')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique('user_id', 'uq_personal_ventas_user_id');
            $table->unique('dni', 'uq_personal_ventas_dni');

            $table->foreign('user_id', 'fk_personal_ventas_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_ventas');
    }
};
