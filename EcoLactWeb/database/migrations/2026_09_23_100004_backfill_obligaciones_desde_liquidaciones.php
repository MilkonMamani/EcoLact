<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Preserva el historial financiero de proveedores al pasar al flujo
 * Configuración -> Pagos: cada liquidación no anulada se refleja como una
 * obligación de pago. Las liquidaciones pagadas quedan PAGADO (con su pago
 * real consolidado, en efectivo como se pagaba); las pendientes quedan
 * PENDIENTE por su saldo. Las tablas liquidaciones, liquidacion_detalles y
 * pagos_proveedor NO se tocan: el portal y la app del proveedor las siguen
 * leyendo. Es re-ejecutable: no duplica lo ya migrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();

        DB::table('liquidaciones as l')
            ->join('periodos_liquidacion as p', 'p.id', '=', 'l.periodo_id')
            ->join('proveedores as pr', 'pr.id', '=', 'l.proveedor_id')
            ->where('l.estado', '<>', 'ANULADA')
            ->select(
                'l.id', 'l.proveedor_id', 'l.estado', 'l.total_litros_entregados', 'l.total_litros_pagables',
                'l.precio_litro_aplicado', 'l.tuvo_incidencia', 'l.subtotal', 'l.total_ajustes', 'l.total_pagar',
                'l.generado_por_user_id', 'l.fecha_calculo', 'l.updated_at',
                'p.fecha_inicio', 'p.fecha_fin', 'pr.nombres', 'pr.apellidos',
            )
            ->orderBy('l.id')
            ->chunkById(200, function ($liquidaciones) use ($ahora): void {
                foreach ($liquidaciones as $liquidacion) {
                    $this->migrar($liquidacion, $ahora);
                }
            }, 'l.id', 'id');
    }

    public function down(): void
    {
        $migradas = DB::table('obligaciones_pago')->whereNull('configuracion_pago_id')->whereNotNull('liquidacion_id')->pluck('id');

        DB::table('pagos')->whereIn('obligacion_pago_id', $migradas)->delete();
        DB::table('obligaciones_pago')->whereIn('id', $migradas)->delete();
    }

    private function migrar(object $liquidacion, $ahora): void
    {
        $inicio = substr((string) $liquidacion->fecha_inicio, 0, 10);
        $clave = 'PROVEEDOR:'.$liquidacion->proveedor_id.':'.$inicio;

        if (DB::table('obligaciones_pago')->where('clave_unicidad', $clave)->exists()) {
            return;
        }

        $pagos = DB::table('pagos_proveedor')
            ->where('liquidacion_id', $liquidacion->id)
            ->where('estado', '<>', 'ANULADO')
            ->orderBy('fecha_pago')
            ->orderBy('id')
            ->get();

        $total = round((float) $liquidacion->total_pagar, 2);
        $pagado = round((float) $pagos->sum('monto'), 2);
        $saldo = round($total - $pagado, 2);
        $estaPagada = $liquidacion->estado === 'PAGADA' || ($pagos->isNotEmpty() && $saldo <= 0);
        $monto = $estaPagada ? $total : $saldo;

        if ($monto <= 0) {
            return;
        }

        $base = [
            'origen' => 'liquidacion_anterior',
            'tipo_calculo' => 'POR_LITROS',
            'litros_entregados' => (float) $liquidacion->total_litros_entregados,
            'litros_pagables' => (float) $liquidacion->total_litros_pagables,
            'precio_litro_aplicado' => (float) $liquidacion->precio_litro_aplicado,
            'tuvo_incidencia' => (bool) $liquidacion->tuvo_incidencia,
            'subtotal' => (float) $liquidacion->subtotal,
            'ajustes' => (float) $liquidacion->total_ajustes,
        ];

        if (! $estaPagada && $pagado > 0) {
            $base['total_liquidacion'] = $total;
            $base['abonos_previos'] = $pagado;
        }

        $obligacionId = DB::table('obligaciones_pago')->insertGetId([
            'tipo_beneficiario' => 'PROVEEDOR',
            'proveedor_id' => $liquidacion->proveedor_id,
            'user_id' => null,
            'rol_id' => null,
            'beneficiario_nombre' => trim($liquidacion->nombres.' '.$liquidacion->apellidos),
            'configuracion_pago_id' => null,
            'liquidacion_id' => $liquidacion->id,
            'periodo_inicio' => $inicio,
            'periodo_fin' => substr((string) $liquidacion->fecha_fin, 0, 10),
            'periodicidad' => 'SEMANAL',
            'concepto' => 'Por litros entregados',
            'base_calculo' => json_encode($base),
            'monto_calculado' => $monto,
            'estado' => $estaPagada ? 'PAGADO' : 'PENDIENTE',
            'clave_unicidad' => $clave,
            'creado_por_user_id' => $liquidacion->generado_por_user_id,
            'created_at' => $liquidacion->fecha_calculo ?? $ahora,
            'updated_at' => $ahora,
        ]);

        if (! $estaPagada) {
            return;
        }

        $ultimo = $pagos->last();

        DB::table('pagos')->insert([
            'obligacion_pago_id' => $obligacionId,
            'fecha_pago' => substr((string) ($ultimo->fecha_pago ?? $liquidacion->updated_at ?? $ahora), 0, 10),
            'monto' => $monto,
            'metodo_pago' => 'EFECTIVO',
            'numero_operacion' => null,
            'observacion' => $ultimo->observacion ?? null,
            'comprobante_path' => null,
            'comprobante_nombre' => null,
            'pago_proveedor_id' => $ultimo->id ?? null,
            'registrado_por_user_id' => $ultimo->registrado_por_user_id ?? $liquidacion->generado_por_user_id,
            'created_at' => $ultimo->created_at ?? $ahora,
            'updated_at' => $ahora,
        ]);
    }
};
