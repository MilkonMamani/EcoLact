<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A partir de esta etapa, un proveedor se registra independientemente
     * de su zona (la zona se deriva de su asignación vigente en
     * ruta_detalles/rutas, gestionada desde el módulo Zonas). zona_id pasa a
     * ser nullable para permitir esa creación inicial sin ruta asignada.
     *
     * proveedores.zona_id NO se elimina en esta etapa: se conserva como
     * caché sincronizada (actualizada automáticamente por
     * AsignarProveedorARuta/QuitarProveedorDeRuta vía el mismo
     * ActualizarProveedor que ya mantenía proveedor_zona_historial) para que
     * los reportes, filtros y políticas existentes que todavía leen esta
     * columna sigan funcionando sin cambios mientras se verifica la
     * transición completa a rutas. Ver docs/rutas-recorridos-acopio.md para
     * el criterio de retiro definitivo de la columna.
     */
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->unsignedBigInteger('zona_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->unsignedBigInteger('zona_id')->nullable(false)->change();
        });
    }
};
