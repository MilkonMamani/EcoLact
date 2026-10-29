<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lleva las compras de insumos ya registradas (C-0001: Cuajo, 8 × Balde 10 L,
 * S/ 480, boleta B001-111) al modelo de Compras y gastos SIN volver a mover
 * nada:
 *
 *   - NO crea movimientos de inventario: las líneas ya tienen el suyo.
 *   - NO crea movimientos de caja: la salida existente (COMPRA_INSUMOS #id)
 *     se enlaza a un pago_compra nuevo vía `movimiento_caja_id`, con su mismo
 *     monto y fecha. Así la compra queda PAGADA y no se puede pagar otra vez.
 *   - NO copia archivos: la foto de la boleta se referencia donde está.
 *
 * Es idempotente (solo completa lo que falta) y no corrige datos ambiguos:
 * si una compra con total no tiene salida de caja, o la salida no coincide
 * con el total, la deja como está calculada y lo informa en el log para
 * revisarla a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        $categoriaInsumos = DB::table('categorias_compra')->where('codigo', 'INSUMOS_PRODUCCION')->value('id');

        DB::table('compras_insumo')->orderBy('id')->each(function (object $compra) use ($categoriaInsumos): void {
            DB::transaction(function () use ($compra, $categoriaInsumos): void {
                $cambios = [];

                if ($compra->codigo === null) {
                    $cambios['codigo'] = 'C-'.str_pad((string) $compra->id, 4, '0', STR_PAD_LEFT);
                }
                if ($compra->categoria_compra_id === null) {
                    $cambios['categoria_compra_id'] = $categoriaInsumos;
                }
                if ($compra->concepto === null) {
                    $cambios['concepto'] = 'Compra de insumos';
                }
                if ($compra->tipo_comprobante === null) {
                    $cambios['tipo_comprobante'] = $compra->numero_comprobante !== null ? 'BOLETA' : 'SIN_COMPROBANTE';
                }
                if ($compra->proveedor_comercial_id === null && $compra->proveedor_comercial !== null && trim($compra->proveedor_comercial) !== '') {
                    $cambios['proveedor_comercial_id'] = $this->proveedorComercial($compra->proveedor_comercial, $compra->ruc);
                }
                // Las compras anteriores siempre ingresaron su stock al confirmarse.
                if ($compra->recibida_at === null) {
                    $cambios['estado_recepcion'] = 'RECIBIDA';
                    $cambios['recibida_at'] = $compra->created_at;
                    $cambios['recibida_por_user_id'] = $compra->registrado_por_user_id;
                }

                DB::table('compra_insumo_detalles as d')
                    ->leftJoin('insumos as i', 'i.id', '=', 'd.insumo_id')
                    ->where('d.compra_insumo_id', $compra->id)
                    ->select('d.id', 'd.descripcion', 'd.subtotal', 'd.precio_unitario', 'd.cantidad_presentaciones', 'i.nombre')
                    ->get()
                    ->each(function (object $detalle): void {
                        $datos = [];
                        if ($detalle->descripcion === null && $detalle->nombre !== null) {
                            $datos['descripcion'] = $detalle->nombre;
                        }
                        if ($detalle->subtotal === null && $detalle->precio_unitario !== null) {
                            $datos['subtotal'] = round((float) $detalle->precio_unitario * (float) $detalle->cantidad_presentaciones, 2);
                        }
                        if ($datos !== []) {
                            DB::table('compra_insumo_detalles')->where('id', $detalle->id)->update($datos);
                        }
                    });

                $movimiento = DB::table('movimientos_caja')
                    ->where('referencia_tipo', 'COMPRA_INSUMOS')
                    ->where('referencia_id', $compra->id)
                    ->first();

                if ($movimiento !== null && ! DB::table('pagos_compra')->where('movimiento_caja_id', $movimiento->id)->exists()) {
                    DB::table('pagos_compra')->insert([
                        'compra_insumo_id' => $compra->id,
                        'monto' => $movimiento->monto,
                        'metodo_pago' => null,
                        'numero_operacion' => null,
                        'fecha' => $movimiento->fecha,
                        'observacion' => 'Pago registrado con la compra, antes de Compras y gastos.',
                        'movimiento_caja_id' => $movimiento->id,
                        'registrado_por_user_id' => $movimiento->registrado_por_user_id,
                        'created_at' => $movimiento->created_at,
                        'updated_at' => $movimiento->updated_at,
                    ]);
                }

                $total = $compra->total !== null ? round((float) $compra->total, 2) : null;
                $pagado = round((float) DB::table('pagos_compra')->where('compra_insumo_id', $compra->id)->sum('monto'), 2);
                $cambios['monto_pagado'] = $pagado;
                $cambios['estado_pago'] = match (true) {
                    $total === null || $total <= 0.0 => 'SIN_IMPORTE',
                    $pagado >= $total => 'PAGADA',
                    $pagado > 0.0 => 'PARCIAL',
                    default => 'PENDIENTE',
                };

                if ($total !== null && $total > 0.0 && abs($pagado - $total) >= 0.01) {
                    Log::warning('Compra histórica con pagos que no coinciden con su total: revisar a mano.', [
                        'compra_id' => $compra->id, 'total' => $total, 'pagado' => $pagado,
                    ]);
                }

                DB::table('compras_insumo')->where('id', $compra->id)->update($cambios);

                if ($compra->imagen_boleta !== null && ! DB::table('comprobantes_compra')->where('disco', 'public')->where('ruta', $compra->imagen_boleta)->exists()) {
                    $extension = strtolower(pathinfo($compra->imagen_boleta, PATHINFO_EXTENSION));
                    DB::table('comprobantes_compra')->insert([
                        'compra_insumo_id' => $compra->id,
                        'tipo' => 'BOLETA',
                        'disco' => 'public',
                        'ruta' => $compra->imagen_boleta,
                        'nombre_original' => basename($compra->imagen_boleta),
                        'mime' => ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$extension] ?? null,
                        'tamano' => null,
                        'procesado_ocr' => $compra->origen === 'BOLETA',
                        'subido_por_user_id' => $compra->registrado_por_user_id,
                        'created_at' => $compra->created_at,
                        'updated_at' => $compra->updated_at,
                    ]);
                }
            });
        });
    }

    public function down(): void
    {
        // Los datos completados no se borran: las columnas y tablas nuevas
        // se quitan con sus propias migraciones.
    }

    private function proveedorComercial(string $razonSocial, ?string $ruc): int
    {
        $razonSocial = trim($razonSocial);
        $ruc = $ruc !== null && trim($ruc) !== '' ? trim($ruc) : null;

        $existente = $ruc !== null
            ? DB::table('proveedores_comerciales')->where('ruc', $ruc)->value('id')
            : DB::table('proveedores_comerciales')->whereNull('ruc')->whereRaw('LOWER(razon_social) = ?', [mb_strtolower($razonSocial)])->value('id');

        return (int) ($existente ?? DB::table('proveedores_comerciales')->insertGetId([
            'razon_social' => $razonSocial,
            'ruc' => $ruc,
            'estado' => 'ACTIVO',
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }
};
