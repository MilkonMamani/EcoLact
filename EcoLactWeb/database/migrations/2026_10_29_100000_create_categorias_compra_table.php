<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos de adquisición de Compras y gastos (insumos de producción, limpieza,
 * consumo interno...). Es un catálogo de la base, no un enum: se puede
 * extender agregando filas (estado ACTIVA/INACTIVA, nunca se borran porque
 * las compras históricas las referencian).
 *
 * `tratamiento_sugerido` es solo lo que propone la pantalla para cada línea
 * nueva; cada línea guarda su propio tratamiento.
 * `categoria_caja` decide en qué categoría de Caja cae el dinero que sale al
 * pagar una compra de este tipo (INSUMOS o GASTOS).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_compra', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->string('codigo', 30)->unique('uq_categorias_compra_codigo');
            $table->string('nombre', 60);
            $table->string('descripcion', 150)->nullable();
            $table->string('tratamiento_sugerido', 15);
            $table->string('categoria_caja', 15);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('estado', 10)->default('ACTIVA');
            $table->timestamps();
        });

        $ahora = now();
        $filas = [
            ['INSUMOS_PRODUCCION', 'Insumos de producción', 'Sal, cuajo, cultivos, envases...', 'INVENTARIO', 'INSUMOS'],
            ['MATERIALES_LIMPIEZA', 'Materiales de limpieza', 'Detergentes, desinfectantes, escobas...', 'CONSUMO', 'GASTOS'],
            ['CONSUMO_INTERNO', 'Consumo interno', 'Refrigerios, gaseosas, útiles de oficina...', 'CONSUMO', 'GASTOS'],
            ['BIENESTAR_PERSONAL', 'Bienestar del personal', 'Canastas navideñas, uniformes, agasajos...', 'CONSUMO', 'GASTOS'],
            ['EQUIPOS_HERRAMIENTAS', 'Equipos y herramientas', 'Bienes que se usan por varios periodos.', 'BIEN', 'GASTOS'],
            ['SERVICIOS', 'Servicios', 'Luz, agua, internet, mantenimiento, transporte...', 'SERVICIO', 'GASTOS'],
            ['OTROS_GASTOS', 'Otros gastos', 'Gastos operativos que no encajan en otro tipo.', 'CONSUMO', 'GASTOS'],
        ];

        foreach ($filas as $orden => [$codigo, $nombre, $descripcion, $tratamiento, $caja]) {
            DB::table('categorias_compra')->insertOrIgnore([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'tratamiento_sugerido' => $tratamiento,
                'categoria_caja' => $caja,
                'orden' => $orden + 1,
                'estado' => 'ACTIVA',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_compra');
    }
};
