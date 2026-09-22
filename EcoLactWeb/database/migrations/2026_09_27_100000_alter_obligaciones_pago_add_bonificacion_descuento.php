<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bonificación y descuento del sueldo del personal: ajustes que el
 * administrador puede fijar por trabajador mientras su obligación de pago
 * esté PENDIENTE, desde "Pagar a trabajadores". No aplican a proveedores
 * (siempre quedan en 0 para esas filas). monto_calculado sigue siendo el
 * total a pagar (sueldo base + bonificación - descuento); al ajustar se
 * recalcula ahí mismo, así el resto del flujo de pago (RegistrarPagoObligacion,
 * comprobantes) no necesita conocer estas dos columnas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('obligaciones_pago', function (Blueprint $table) {
            $table->decimal('bonificacion', 12, 2)->default(0)->after('monto_calculado');
            $table->decimal('descuento', 12, 2)->default(0)->after('bonificacion');
        });
    }

    public function down(): void
    {
        Schema::table('obligaciones_pago', function (Blueprint $table) {
            $table->dropColumn(['bonificacion', 'descuento']);
        });
    }
};
