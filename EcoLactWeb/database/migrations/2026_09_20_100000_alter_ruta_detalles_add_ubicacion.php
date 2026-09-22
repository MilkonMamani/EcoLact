<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La ubicación deja de ser un dato del proveedor y pasa a ser el punto
     * de recolección DENTRO de su ruta: se registra al asignar el proveedor
     * a una zona/ruta (módulo Zonas) y desaparece junto con la asignación
     * cuando se le quita de la ruta. Por eso vive en ruta_detalles.
     *
     * Migración de datos: las coordenadas que ya existían en proveedores
     * (latitud/longitud/referencia_ubicacion) se copian a la asignación
     * vigente de cada proveedor. Las columnas de proveedores NO se eliminan
     * aquí (para no destruir coordenadas de proveedores todavía sin ruta):
     * la aplicación ya no las lee ni las escribe, y pueden retirarse en una
     * migración posterior una vez verificado el traspaso.
     */
    public function up(): void
    {
        Schema::table('ruta_detalles', function (Blueprint $table) {
            $table->decimal('latitud', 10, 7)->nullable()->after('orden_visita');
            $table->decimal('longitud', 10, 7)->nullable()->after('latitud');
            $table->string('referencia_ubicacion', 255)->nullable()->after('longitud');
        });

        if (! Schema::hasColumn('proveedores', 'latitud')) {
            return;
        }

        DB::table('proveedores')
            ->whereNotNull('latitud')
            ->whereNotNull('longitud')
            ->orderBy('id')
            ->get(['id', 'latitud', 'longitud', 'referencia_ubicacion'])
            ->each(function (object $proveedor): void {
                DB::table('ruta_detalles')
                    ->where('proveedor_id', $proveedor->id)
                    ->update([
                        'latitud' => $proveedor->latitud,
                        'longitud' => $proveedor->longitud,
                        'referencia_ubicacion' => $proveedor->referencia_ubicacion,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('ruta_detalles', function (Blueprint $table) {
            $table->dropColumn(['latitud', 'longitud', 'referencia_ubicacion']);
        });
    }
};
