<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comunicado_destinatarios', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('comunicado_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('leido')->default(0);
            $table->dateTime('fecha_lectura')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['comunicado_id', 'user_id'], 'uq_comunicado_destinatarios_comunicado_id_user_id');
            $table->index(['user_id', 'leido'], 'idx_comunicado_destinatarios_user_id_leido');

            $table->foreign('comunicado_id', 'fk_comunicado_destinatarios_comunicado_id')
                ->references('id')->on('comunicados')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('user_id', 'fk_comunicado_destinatarios_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comunicado_destinatarios');
    }
};
