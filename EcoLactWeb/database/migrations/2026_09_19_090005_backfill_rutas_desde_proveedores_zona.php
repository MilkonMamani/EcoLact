<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migración de DATOS (no de esquema): por cada zona que ya tenga
     * proveedores asignados por el antiguo proveedores.zona_id, crea una
     * "Ruta principal" y traslada esos proveedores a ruta_detalles con un
     * orden inicial determinista (por proveedores.id ascendente, es decir
     * por antigüedad de registro), dejando trazabilidad en
     * proveedor_ruta_historial. No inventa ubicaciones ni historial que no
     * pueda reconstruirse: solo usa datos que ya existían en la tabla
     * proveedores.
     *
     * En un entorno de pruebas (SQLite en memoria, sin datos previos) esta
     * migración no encuentra proveedores y no hace nada, como se espera.
     *
     * LIMITACIÓN DE REVERSIÓN: down() es intencionalmente un no-op. Deshacer
     * un backfill de datos borrando lo insertado arriesgaría eliminar
     * ediciones legítimas hechas después por el módulo Zonas/Rutas (un
     * traslado, una reordenación). Antes de ejecutar esta migración en una
     * base de datos con datos reales, haz un respaldo.
     */
    public function up(): void
    {
        $zonas = DB::table('zonas')->orderBy('id')->get(['id']);

        foreach ($zonas as $zona) {
            $proveedores = DB::table('proveedores')
                ->where('zona_id', $zona->id)
                ->orderBy('id')
                ->get(['id', 'fecha_registro']);

            if ($proveedores->isEmpty()) {
                continue;
            }

            $ahora = now();

            $rutaId = DB::table('rutas')->insertGetId([
                'zona_id' => $zona->id,
                'nombre' => 'Ruta principal',
                'descripcion' => 'Ruta creada automáticamente al migrar las asignaciones previas por zona. Puede renombrarse, dividirse o reordenarse desde el módulo Zonas.',
                'estado' => 'ACTIVO',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            $orden = 1;

            foreach ($proveedores as $proveedor) {
                DB::table('ruta_detalles')->insert([
                    'ruta_id' => $rutaId,
                    'proveedor_id' => $proveedor->id,
                    'orden_visita' => $orden,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);

                DB::table('proveedor_ruta_historial')->insert([
                    'proveedor_id' => $proveedor->id,
                    'ruta_id' => $rutaId,
                    'fecha_inicio' => $proveedor->fecha_registro ?? $ahora->toDateString(),
                    'fecha_fin' => null,
                    'motivo' => 'Migración inicial: asignación heredada de la zona.',
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);

                $orden++;
            }
        }
    }

    public function down(): void
    {
        // Intencionalmente vacío: ver nota de limitación de reversión arriba.
    }
};
