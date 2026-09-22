<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prepara `insumos` para el nuevo modelo de inventario:
 *
 *  - `codigo`: identificador estable para insumos del sistema (LECHE), de modo
 *    que las reglas críticas no dependan del nombre.
 *  - `tipo`: MATERIA_PRIMA / INSUMO / ENVASE / OTRO.
 *  - `descripcion`: texto libre opcional.
 *  - `unidad_medida` pasa a llamarse `unidad_base`: el stock y todos los
 *    movimientos se guardan SIEMPRE en esa unidad (L, ml, kg, g, unidad).
 *    Los valores antiguos de texto libre ("Kl", "litros"...) se normalizan a
 *    un código válido; lo irreconocible queda como "unidad" (nunca se
 *    inventa una conversión).
 *  - `stock_actual`/`stock_minimo` ganan precisión (4 decimales) porque las
 *    cantidades se normalizan a unidades base pequeñas (ml, g).
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const EQUIVALENCIAS = [
        'l' => 'L', 'lt' => 'L', 'lts' => 'L', 'litro' => 'L', 'litros' => 'L',
        'ml' => 'ml', 'mililitro' => 'ml', 'mililitros' => 'ml',
        'kg' => 'kg', 'kl' => 'kg', 'kilo' => 'kg', 'kilos' => 'kg', 'kilogramo' => 'kg', 'kilogramos' => 'kg',
        'g' => 'g', 'gr' => 'g', 'gramo' => 'g', 'gramos' => 'g',
        'unidad' => 'unidad', 'unidades' => 'unidad', 'und' => 'unidad', 'u' => 'unidad', 'uni' => 'unidad',
    ];

    public function up(): void
    {
        Schema::table('insumos', function (Blueprint $table) {
            $table->string('codigo', 30)->nullable()->after('id');
            $table->string('tipo', 20)->default('INSUMO')->after('nombre');
            $table->text('descripcion')->nullable()->after('tipo');
        });

        foreach (DB::table('insumos')->get(['id', 'unidad_medida']) as $insumo) {
            $clave = mb_strtolower(trim((string) $insumo->unidad_medida));

            DB::table('insumos')->where('id', $insumo->id)->update([
                'unidad_medida' => self::EQUIVALENCIAS[$clave] ?? 'unidad',
            ]);
        }

        Schema::table('insumos', function (Blueprint $table) {
            $table->renameColumn('unidad_medida', 'unidad_base');
        });

        Schema::table('insumos', function (Blueprint $table) {
            $table->decimal('stock_actual', 14, 4)->default(0)->change();
            $table->decimal('stock_minimo', 14, 4)->default(0)->change();
            $table->unique('codigo', 'uq_insumos_codigo');
        });
    }

    public function down(): void
    {
        Schema::table('insumos', function (Blueprint $table) {
            $table->dropUnique('uq_insumos_codigo');
        });

        Schema::table('insumos', function (Blueprint $table) {
            $table->decimal('stock_actual', 12, 2)->default(0)->change();
            $table->decimal('stock_minimo', 12, 2)->default(0)->change();
            $table->renameColumn('unidad_base', 'unidad_medida');
        });

        Schema::table('insumos', function (Blueprint $table) {
            $table->dropColumn(['codigo', 'tipo', 'descripcion']);
        });
    }
};
