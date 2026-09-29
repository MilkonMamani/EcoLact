<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $jueves = match (DB::connection()->getDriverName()) {
            'sqlite' => "date(periodo_inicio, '-' || ((CAST(strftime('%w', periodo_inicio) AS INTEGER) + 3) % 7) || ' days')",
            'mysql', 'mariadb' => 'DATE_SUB(periodo_inicio, INTERVAL MOD(DAYOFWEEK(periodo_inicio) + 2, 7) DAY)',
            default => throw new RuntimeException('Motor no soportado para la semana canónica de proveedores.'),
        };
        Schema::table('obligaciones_pago', function (Blueprint $table) use ($jueves): void {
            $table->boolean('excepcion_semanal_historica')->default(false);
            $table->date('semana_proveedor')->nullable()->virtualAs(
                "CASE WHEN tipo_beneficiario = 'PROVEEDOR' AND excepcion_semanal_historica = 0 THEN {$jueves} ELSE NULL END",
            );
        });
    }

    public function down(): void
    {
        Schema::table('obligaciones_pago', function (Blueprint $table): void {
            $table->dropColumn('semana_proveedor');
            $table->dropColumn('excepcion_semanal_historica');
        });
    }
};
