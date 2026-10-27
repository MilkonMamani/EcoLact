<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Respuestas de las escrituras de la app móvil que llegan con la cabecera
 * `Idempotency-Key` (ver App\Http\Middleware\IdempotenciaApi). La app reenvía
 * la MISMA clave en cada reintento de una operación offline: si la primera
 * petición llegó pero se perdió la respuesta, el reintento recibe la respuesta
 * guardada y la operación (iniciar/finalizar producción, compra de insumos)
 * nunca se registra dos veces.
 *
 * `estado_http` null = la petición con esa clave todavía se está procesando.
 * Solo se guardan respuestas exitosas: un 422 se puede corregir y reintentar
 * con la misma clave.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_idempotencia', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('clave', 100);
            $table->string('ruta', 150);
            $table->unsignedSmallInteger('estado_http')->nullable();
            $table->longText('respuesta')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'clave'], 'uq_api_idempotencia_usuario_clave');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_idempotencia');
    }
};
