<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot del control de jornadas del personal, para que Blade nunca tenga
 * que calcular: cuántos días se llevan trabajados en el periodo, cuánto vale
 * un día (sueldo_base / 30, recalculado mientras la obligación esté abierta)
 * y cuánto se acumuló (dias_trabajados * valor_dia). monto_calculado sigue
 * siendo el total a pagar (acumulado + bonificación - descuento); estas tres
 * columnas solo se usan para personal — en proveedor quedan en su valor por
 * defecto y se ignoran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('obligaciones_pago', function (Blueprint $table) {
            $table->unsignedInteger('dias_trabajados')->default(0)->after('descuento');
            $table->decimal('valor_dia', 12, 2)->nullable()->after('dias_trabajados');
            $table->decimal('acumulado', 12, 2)->nullable()->after('valor_dia');
        });
    }

    public function down(): void
    {
        Schema::table('obligaciones_pago', function (Blueprint $table) {
            $table->dropColumn(['dias_trabajados', 'valor_dia', 'acumulado']);
        });
    }
};
