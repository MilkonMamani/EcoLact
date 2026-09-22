<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convierte cada producto de `producto_insumos` en una receta v1.
 *
 * Lo que SÍ se puede afirmar matemáticamente: `cantidad_por_unidad` era la
 * cantidad de insumo necesaria por UNIDAD de producto (la fórmula antigua
 * multiplicaba cantidad_por_unidad x cantidad producida). Por eso la receta
 * migrada tiene rendimiento = 1 unidad del producto y cada ingrediente con su
 * cantidad, expresada en la unidad base del insumo.
 *
 * Lo que NO se puede inventar: la receta antigua nunca incluía la leche (la
 * leche se anotaba a mano por lote). Como una receta sin leche descontaría
 * un insumo pero no el principal, las recetas migradas quedan INACTIVAS y
 * con una descripción que indica al administrador qué completar. Ningún
 * producto queda produciéndose con una fórmula incompleta hasta que alguien
 * agregue la leche y la active.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('producto_insumos')) {
            return;
        }

        $actorId = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('roles.nombre', 'ADMINISTRADOR')
            ->orderBy('users.id')
            ->value('users.id') ?? DB::table('users')->min('id');

        if ($actorId === null) {
            return;
        }

        $productoIds = DB::table('producto_insumos')->distinct()->pluck('producto_id');

        foreach ($productoIds as $productoId) {
            if (DB::table('recetas')->where('producto_id', $productoId)->exists()) {
                continue;
            }

            $producto = DB::table('productos')->where('id', $productoId)->first();

            if ($producto === null) {
                continue;
            }

            $recetaId = DB::table('recetas')->insertGetId([
                'producto_id' => $productoId,
                'nombre' => mb_substr('Receta '.$producto->nombre, 0, 120),
                'descripcion' => 'Migrada automáticamente de la receta anterior. La receta anterior no incluía la leche: agregue la leche y los demás ingredientes que falten y luego actívela.',
                'rendimiento_cantidad' => 1,
                'rendimiento_unidad' => $producto->unidad_medida,
                'version' => 1,
                'estado' => 'INACTIVA',
                'producto_activo_id' => null,
                'created_by_user_id' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (DB::table('producto_insumos')->where('producto_id', $productoId)->get() as $fila) {
                $insumo = DB::table('insumos')->where('id', $fila->insumo_id)->first();

                if ($insumo === null) {
                    continue;
                }

                DB::table('receta_detalles')->insert([
                    'receta_id' => $recetaId,
                    'insumo_id' => $fila->insumo_id,
                    'cantidad' => $fila->cantidad_por_unidad,
                    'unidad' => $insumo->unidad_base,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Migración de datos: las recetas migradas pueden haberse editado.
    }
};
