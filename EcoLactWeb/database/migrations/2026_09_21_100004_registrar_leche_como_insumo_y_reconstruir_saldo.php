<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Convierte la LECHE en un insumo del sistema (codigo = LECHE, MATERIA_PRIMA,
 * unidad base L) y deja el inventario de insumos coherente con la historia.
 *
 * DECISIÓN DE SALDO INICIAL (documentada porque es delicada):
 *
 * Hasta ahora la "leche disponible" se calculaba en vivo como
 *   litros de todas las entregas recibidas - litros de todos los lotes.
 * Una entrega contaba como recibida salvo que estuviera ANULADA o su análisis
 * confirmado fuera RECHAZADA (esa leche nunca se acopió físicamente). Ese
 * cálculo era exacto, así que el saldo SÍ se puede reconstruir sin inventar
 * nada: se generan movimientos históricos con las mismas reglas.
 *
 *  - Por cada entrega que contaba como recibida: ENTRADA / INGRESO_ACOPIO,
 *    fechada con la fecha de la entrega y con origen ENTREGA.
 *  - Por cada lote con litros: SALIDA / CONSUMO_PRODUCCION, más su fila en
 *    lote_produccion_insumos (snapshot del consumo del lote).
 *  - Si la reconstrucción diera negativa (datos inconsistentes), se registra
 *    un AJUSTE_ENTRADA explícito "Ajuste inicial" por la diferencia, para no
 *    dejar nunca un stock negativo ni ocultar la anomalía.
 *
 * Los demás insumos tenían stock_actual pero NINGÚN movimiento que lo
 * respaldara (el alta con stock inicial no generaba movimiento). Para que los
 * movimientos sean la única fuente de verdad, se registra un AJUSTE de
 * "Saldo inicial de migración" por la diferencia entre el stock guardado y
 * la suma de sus movimientos.
 *
 * Es idempotente: si Leche ya tiene movimientos no se reconstruye.
 */
return new class extends Migration
{
    private const EPSILON = 0.00005;

    public function up(): void
    {
        $actorId = $this->actorId();
        $leche = $this->obtenerOCrearLeche();

        if (DB::table('insumo_movimientos')->where('insumo_id', $leche->id)->doesntExist()) {
            $this->reconstruirSaldoLeche((int) $leche->id, $actorId);
        }

        $this->conciliarSaldosDeOtrosInsumos((int) $leche->id, $actorId);
    }

    public function down(): void
    {
        // Migración de datos: no se revierte para no borrar movimientos que
        // el sistema ya pudo haber usado. La estructura la revierten las
        // migraciones de esquema anteriores.
    }

    private function actorId(): ?int
    {
        $admin = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('roles.nombre', 'ADMINISTRADOR')
            ->orderBy('users.id')
            ->value('users.id');

        return $admin !== null ? (int) $admin : (DB::table('users')->min('id') !== null ? (int) DB::table('users')->min('id') : null);
    }

    private function obtenerOCrearLeche(): object
    {
        $porCodigo = DB::table('insumos')->where('codigo', 'LECHE')->first();

        if ($porCodigo !== null) {
            return $porCodigo;
        }

        $porNombre = DB::table('insumos')->whereRaw('LOWER(nombre) = ?', ['leche'])->first();

        if ($porNombre !== null && $porNombre->unidad_base === 'L') {
            DB::table('insumos')->where('id', $porNombre->id)->update([
                'codigo' => 'LECHE',
                'tipo' => 'MATERIA_PRIMA',
                'updated_at' => now(),
            ]);

            return DB::table('insumos')->where('id', $porNombre->id)->first();
        }

        $nombre = $porNombre === null ? 'Leche' : 'Leche (materia prima)';

        $id = DB::table('insumos')->insertGetId([
            'codigo' => 'LECHE',
            'nombre' => $nombre,
            'tipo' => 'MATERIA_PRIMA',
            'descripcion' => 'Leche cruda acopiada. Su stock aumenta automáticamente con cada entrega de proveedores.',
            'unidad_base' => 'L',
            'stock_actual' => 0,
            'stock_minimo' => 0,
            'estado' => 'ACTIVO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('insumos')->where('id', $id)->first();
    }

    private function reconstruirSaldoLeche(int $lecheId, ?int $actorId): void
    {
        $entregas = DB::table('entregas')
            ->leftJoin('analisis_calidad', 'analisis_calidad.entrega_id', '=', 'entregas.id')
            ->where('entregas.estado', '!=', 'ANULADA')
            ->where(function ($consulta): void {
                $consulta->whereNull('analisis_calidad.resultado')
                    ->orWhere('analisis_calidad.resultado', '!=', 'RECHAZADA');
            })
            ->get(['entregas.id', 'entregas.litros', 'entregas.fecha_hora', 'entregas.registrado_por_user_id']);

        $lotes = DB::table('lotes_produccion')->where('litros_leche_utilizados', '>', 0)->get();

        if ($entregas->isEmpty() && $lotes->isEmpty()) {
            return;
        }

        $saldo = 0.0;

        foreach ($entregas as $entrega) {
            DB::table('insumo_movimientos')->insert([
                'insumo_id' => $lecheId,
                'tipo' => 'ENTRADA',
                'motivo' => 'INGRESO_ACOPIO',
                'cantidad' => $entrega->litros,
                'lote_produccion_id' => null,
                'origen_tipo' => 'ENTREGA',
                'origen_id' => $entrega->id,
                'fecha' => $entrega->fecha_hora,
                'observacion' => 'Ingreso reconstruido desde la entrega histórica #'.$entrega->id.'.',
                'registrado_por_user_id' => $entrega->registrado_por_user_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $saldo += (float) $entrega->litros;
        }

        foreach ($lotes as $lote) {
            DB::table('insumo_movimientos')->insert([
                'insumo_id' => $lecheId,
                'tipo' => 'SALIDA',
                'motivo' => 'CONSUMO_PRODUCCION',
                'cantidad' => $lote->litros_leche_utilizados,
                'lote_produccion_id' => $lote->id,
                'origen_tipo' => 'LOTE_PRODUCCION',
                'origen_id' => $lote->id,
                'fecha' => $lote->fecha_hora_inicio,
                'observacion' => 'Consumo reconstruido desde el lote histórico '.$lote->codigo.'.',
                'registrado_por_user_id' => $lote->registrado_por_user_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('lote_produccion_insumos')->insert([
                'lote_produccion_id' => $lote->id,
                'insumo_id' => $lecheId,
                'cantidad_requerida' => $lote->litros_leche_utilizados,
                'cantidad_usada' => $lote->litros_leche_utilizados,
                'unidad_base' => 'L',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $saldo -= (float) $lote->litros_leche_utilizados;
        }

        if ($saldo < -self::EPSILON && $actorId !== null) {
            DB::table('insumo_movimientos')->insert([
                'insumo_id' => $lecheId,
                'tipo' => 'ENTRADA',
                'motivo' => 'AJUSTE_ENTRADA',
                'cantidad' => round(-$saldo, 4),
                'lote_produccion_id' => null,
                'origen_tipo' => null,
                'origen_id' => null,
                'fecha' => now(),
                'observacion' => 'Ajuste inicial: la historia registraba más leche usada que recibida.',
                'registrado_por_user_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $saldo = 0.0;
        }

        DB::table('insumos')->where('id', $lecheId)->update([
            'stock_actual' => round(max($saldo, 0.0), 4),
            'updated_at' => now(),
        ]);
    }

    private function conciliarSaldosDeOtrosInsumos(int $lecheId, ?int $actorId): void
    {
        if ($actorId === null) {
            return;
        }

        foreach (DB::table('insumos')->where('id', '!=', $lecheId)->get(['id', 'stock_actual']) as $insumo) {
            $entradas = (float) DB::table('insumo_movimientos')->where('insumo_id', $insumo->id)->where('tipo', 'ENTRADA')->sum('cantidad');
            $salidas = (float) DB::table('insumo_movimientos')->where('insumo_id', $insumo->id)->where('tipo', 'SALIDA')->sum('cantidad');
            $diferencia = round((float) $insumo->stock_actual - ($entradas - $salidas), 4);

            if (abs($diferencia) < self::EPSILON) {
                continue;
            }

            DB::table('insumo_movimientos')->insert([
                'insumo_id' => $insumo->id,
                'tipo' => $diferencia > 0 ? 'ENTRADA' : 'SALIDA',
                'motivo' => $diferencia > 0 ? 'AJUSTE_ENTRADA' : 'AJUSTE_SALIDA',
                'cantidad' => abs($diferencia),
                'lote_produccion_id' => null,
                'origen_tipo' => null,
                'origen_id' => null,
                'fecha' => now(),
                'observacion' => 'Saldo inicial de migración: concilia el stock registrado con el historial de movimientos.',
                'registrado_por_user_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
