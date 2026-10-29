<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `compras_insumo` pasa a ser la cabecera de TODA adquisición (Compras y
 * gastos), no solo de insumos. Se conserva el nombre de la tabla para no
 * romper sus relaciones: insumo_movimientos.origen (COMPRA_INSUMO #id),
 * movimientos_caja (COMPRA_INSUMOS #id) y la app móvil.
 *
 *   codigo            "C-0001": el mismo que ya mostraba Caja.
 *   categoria         tipo de adquisición (categorias_compra).
 *   proveedor_comercial_id  tienda normalizada; proveedor_comercial/ruc quedan
 *                     como fotografía de lo que decía el comprobante.
 *   tipo_comprobante  BOLETA, FACTURA, RECIBO, TICKET o SIN_COMPROBANTE.
 *   impuesto          IGV u otro impuesto que se suma a las líneas (opcional).
 *   estado_recepcion  RECIBIDA / PENDIENTE (aún no llega: no hay stock) /
 *                     NO_APLICA (no tiene líneas de inventario).
 *   estado_pago, monto_pagado  resumen de pagos_compra para filtrar y listar
 *                     sin sumar; lo mantiene RegistrarPagoCompra.
 *
 * Solo agrega columnas nullable o con valor por defecto: los registros
 * existentes no cambian aquí (el backfill va en otra migración).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras_insumo', function (Blueprint $table) {
            $table->string('codigo', 12)->nullable()->after('id')->unique('uq_compras_insumo_codigo');
            $table->unsignedBigInteger('categoria_compra_id')->nullable()->after('origen');
            $table->unsignedBigInteger('proveedor_comercial_id')->nullable()->after('categoria_compra_id');
            $table->string('tipo_comprobante', 20)->nullable()->after('ruc');
            $table->char('moneda', 3)->default('PEN')->after('fecha');
            $table->string('concepto', 150)->nullable()->after('moneda');
            $table->decimal('impuesto', 12, 2)->default(0)->after('concepto');
            $table->string('estado_recepcion', 12)->default('RECIBIDA')->after('total');
            $table->dateTime('recibida_at')->nullable()->after('estado_recepcion');
            $table->unsignedBigInteger('recibida_por_user_id')->nullable()->after('recibida_at');
            $table->string('estado_pago', 12)->default('SIN_IMPORTE')->after('recibida_por_user_id');
            $table->decimal('monto_pagado', 12, 2)->default(0)->after('estado_pago');

            $table->index(['estado_pago', 'fecha'], 'idx_compras_insumo_estado_pago_fecha');
            $table->index(['categoria_compra_id', 'fecha'], 'idx_compras_insumo_categoria_fecha');

            $table->foreign('categoria_compra_id', 'fk_compras_insumo_categoria_compra_id')
                ->references('id')->on('categorias_compra')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('proveedor_comercial_id', 'fk_compras_insumo_proveedor_comercial_id')
                ->references('id')->on('proveedores_comerciales')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreign('recibida_por_user_id', 'fk_compras_insumo_recibida_por_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('compras_insumo', function (Blueprint $table) {
            $table->dropForeign('fk_compras_insumo_categoria_compra_id');
            $table->dropForeign('fk_compras_insumo_proveedor_comercial_id');
            $table->dropForeign('fk_compras_insumo_recibida_por_user_id');
            $table->dropIndex('idx_compras_insumo_estado_pago_fecha');
            $table->dropIndex('idx_compras_insumo_categoria_fecha');
            $table->dropUnique('uq_compras_insumo_codigo');
            $table->dropColumn([
                'codigo', 'categoria_compra_id', 'proveedor_comercial_id', 'tipo_comprobante', 'moneda', 'concepto',
                'impuesto', 'estado_recepcion', 'recibida_at', 'recibida_por_user_id', 'estado_pago', 'monto_pagado',
            ]);
        });
    }
};
