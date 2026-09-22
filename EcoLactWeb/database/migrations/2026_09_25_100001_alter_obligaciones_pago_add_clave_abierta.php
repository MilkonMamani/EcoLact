<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un proveedor puede tener varios pagos en la misma semana (uno ya PAGADO y otro
 * PENDIENTE con las entregas posteriores), pero a lo sumo UN pendiente abierto
 * por semana. `clave_abierta` lo garantiza: solo se llena mientras el pago
 * está PENDIENTE (y es nula al pagarse, por lo que los pagados no chocan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('obligaciones_pago', function (Blueprint $table) {
            $table->string('clave_abierta', 120)->nullable()->after('clave_unicidad');
            $table->unique('clave_abierta', 'uq_obligaciones_pago_clave_abierta');
        });

        DB::table('obligaciones_pago')
            ->where('tipo_beneficiario', 'PROVEEDOR')
            ->where('estado', 'PENDIENTE')
            ->whereNull('liquidacion_id')
            ->orderBy('id')
            ->each(function ($fila): void {
                DB::table('obligaciones_pago')->where('id', $fila->id)->update([
                    'clave_abierta' => 'PROVEEDOR:'.$fila->proveedor_id.':'.substr((string) $fila->periodo_inicio, 0, 10),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('obligaciones_pago', function (Blueprint $table) {
            $table->dropUnique('uq_obligaciones_pago_clave_abierta');
            $table->dropColumn('clave_abierta');
        });
    }
};
