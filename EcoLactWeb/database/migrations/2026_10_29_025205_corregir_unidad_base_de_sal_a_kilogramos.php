<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Corrige la SAL creada con unidad base de volumen (ml): la sal se controla
 * por masa, así que pasa a kilogramos (kg).
 *
 * Solo cambia el dato si no hay NADA expresado en la unidad anterior: stock
 * en 0 y sin movimientos, presentaciones, compras, recetas ni consumos. Entre
 * masa y volumen no existe conversión segura, así que con historial NO se
 * toca nada: queda una advertencia en el log para corregirlo a mano. Leche,
 * Cuajo y cualquier otro insumo no se modifican. El stock mínimo se conserva
 * tal cual (el administrador lo revisa desde Editar insumo).
 *
 * Cada corrección queda en `auditorias`; down() la deshace solo si el
 * insumo sigue sin historial (si ya se compró sal en kg, no se revierte).
 */
return new class extends Migration
{
    private const ACCION = 'CAMBIO_UNIDAD_BASE_INSUMO';

    private const MOTIVO = 'Migración: la sal se controla por masa (kg), no por volumen.';

    private const MOTIVO_REVERSION = 'Reversión de la migración de unidad base de la sal.';

    private const DEPENDENCIAS = ['insumo_movimientos', 'presentaciones_insumo', 'compra_insumo_detalles', 'receta_detalles', 'lote_produccion_insumos'];

    public function up(): void
    {
        $sales = DB::table('insumos')
            ->whereNull('codigo')
            ->whereRaw('LOWER(TRIM(nombre)) = ?', ['sal'])
            ->whereIn('unidad_base', ['ml', 'L'])
            ->get();

        foreach ($sales as $sal) {
            if (! $this->sinHistorial($sal)) {
                Log::warning("Sal (insumo #{$sal->id}) sigue en {$sal->unidad_base}: tiene stock o historial y no se puede pasar a kg sin una corrección explícita.");

                continue;
            }

            DB::transaction(function () use ($sal): void {
                DB::table('insumos')->where('id', $sal->id)->update(['unidad_base' => 'kg', 'updated_at' => now()]);
                $this->auditar($sal, $sal->unidad_base, 'kg', self::MOTIVO);
            });
        }
    }

    public function down(): void
    {
        $correcciones = DB::table('auditorias')
            ->where('accion', self::ACCION)
            ->where('tabla_afectada', 'insumos')
            ->where('motivo', self::MOTIVO)
            ->orderByDesc('id')
            ->get();

        foreach ($correcciones as $correccion) {
            $anterior = json_decode((string) $correccion->datos_anteriores, true)['unidad_base'] ?? null;
            $sal = DB::table('insumos')->where('id', $correccion->registro_id)->first();

            if ($anterior === null || $sal === null || $sal->unidad_base !== 'kg' || ! $this->sinHistorial($sal)) {
                continue;
            }

            DB::transaction(function () use ($sal, $anterior): void {
                DB::table('insumos')->where('id', $sal->id)->update(['unidad_base' => $anterior, 'updated_at' => now()]);
                $this->auditar($sal, 'kg', $anterior, self::MOTIVO_REVERSION);
            });
        }
    }

    private function sinHistorial(object $insumo): bool
    {
        if ((float) $insumo->stock_actual !== 0.0) {
            return false;
        }

        foreach (self::DEPENDENCIAS as $tabla) {
            if (DB::table($tabla)->where('insumo_id', $insumo->id)->exists()) {
                return false;
            }
        }

        return true;
    }

    private function auditar(object $insumo, string $desde, string $hasta, string $motivo): void
    {
        DB::table('auditorias')->insert([
            'user_id' => null,
            'accion' => self::ACCION,
            'tabla_afectada' => 'insumos',
            'registro_id' => $insumo->id,
            'datos_anteriores' => json_encode(['unidad_base' => $desde, 'stock_minimo' => (float) $insumo->stock_minimo]),
            'datos_nuevos' => json_encode(['unidad_base' => $hasta, 'stock_minimo' => (float) $insumo->stock_minimo]),
            'motivo' => $motivo,
            'created_at' => now(),
        ]);
    }
};
