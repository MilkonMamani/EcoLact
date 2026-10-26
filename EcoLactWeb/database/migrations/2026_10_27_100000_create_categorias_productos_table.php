<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Categorías de producto terminado (Quesos, Yogures, Mantequillas...). Antes
 * el tipo era texto libre en productos.tipo_producto; ahora el producto apunta
 * a su categoría por `categoria_id`.
 *
 * `nombre_normalizado` (minúsculas, espacios colapsados) es único: impide
 * "Quesos", "quesos" y " Quesos  " como categorías distintas.
 *
 * Los valores existentes de tipo_producto se migran a categorías (Queso →
 * Quesos, Yogurt → Yogures, Mantequilla → Mantequillas; cualquier otro se
 * crea tal cual). La columna tipo_producto NO se elimina: el listado y la
 * ficha de productos todavía la leen.
 */
return new class extends Migration
{
    private const BASE = ['Quesos', 'Yogures', 'Mantequillas'];

    private const ALIAS = [
        'queso' => 'Quesos',
        'quesos' => 'Quesos',
        'yogurt' => 'Yogures',
        'yogurts' => 'Yogures',
        'yogur' => 'Yogures',
        'yogures' => 'Yogures',
        'mantequilla' => 'Mantequillas',
        'mantequillas' => 'Mantequillas',
    ];

    public function up(): void
    {
        Schema::create('categorias_productos', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->string('nombre', 60);
            $table->string('nombre_normalizado', 60)->unique('uq_categorias_productos_nombre_normalizado');
            $table->string('estado', 10)->default('ACTIVA');
            $table->timestamps();
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->unsignedBigInteger('categoria_id')->nullable()->after('tipo_producto');

            $table->foreign('categoria_id')
                ->references('id')->on('categorias_productos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        foreach (self::BASE as $nombre) {
            $this->categoria($nombre);
        }

        $tipos = DB::table('productos')->whereNotNull('tipo_producto')->distinct()->pluck('tipo_producto');

        foreach ($tipos as $tipo) {
            $nombre = trim((string) preg_replace('/\s+/', ' ', $tipo));

            if ($nombre === '') {
                continue;
            }

            $id = $this->categoria(self::ALIAS[$this->normalizar($nombre)] ?? $nombre);

            DB::table('productos')->where('tipo_producto', $tipo)->update(['categoria_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropForeign(['categoria_id']);
            $table->dropColumn('categoria_id');
        });

        Schema::dropIfExists('categorias_productos');
    }

    private function categoria(string $nombre): int
    {
        $normalizado = $this->normalizar($nombre);
        $id = DB::table('categorias_productos')->where('nombre_normalizado', $normalizado)->value('id');

        return $id ?? DB::table('categorias_productos')->insertGetId([
            'nombre' => $nombre,
            'nombre_normalizado' => $normalizado,
            'estado' => 'ACTIVA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function normalizar(string $nombre): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $nombre)));
    }
};
