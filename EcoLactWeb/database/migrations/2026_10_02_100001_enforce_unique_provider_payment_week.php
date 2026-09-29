<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicados = DB::table('obligaciones_pago')->whereNotNull('semana_proveedor')
            ->select('proveedor_id', 'semana_proveedor')->groupBy('proveedor_id', 'semana_proveedor')
            ->havingRaw('COUNT(*) > 1')->get();
        if ($duplicados->isNotEmpty()) {
            throw new RuntimeException('Hay semanas duplicadas. Ejecute pagos:auditar-semanas y resuelva los grupos antes de instalar el índice. No se ha modificado ningún pago.');
        }
        Schema::table('obligaciones_pago', function (Blueprint $table): void {
            $table->unique(['proveedor_id', 'semana_proveedor'], 'uq_proveedor_semana_pago');
        });
    }

    public function down(): void
    {
        Schema::table('obligaciones_pago', function (Blueprint $table): void {
            $table->dropUnique('uq_proveedor_semana_pago');
        });
    }
};
