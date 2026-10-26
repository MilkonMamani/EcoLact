<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El sueldo del personal pasa a configurarse POR TRABAJADOR (user_id), no por rol.
 *
 * Para que nadie deje de cobrar, cada configuración de sueldo por rol que siga
 * ACTIVA se copia a los trabajadores reales de ese rol (usuario activo con ficha
 * ACTIVA en su tabla; para un rol sin ficha, como el administrador, el usuario
 * activo), con el mismo sueldo, vigencia y observación. Las filas por
 * rol NO se modifican ni se borran: quedan como historial (las obligaciones ya
 * generadas siguen apuntando a ellas) y ya no se usan para calcular sueldos.
 */
return new class extends Migration
{
    /** Tabla de la ficha de cada rol de Trabajadores. */
    private const FICHA_POR_ROL = [
        'ACOPIADOR' => 'acopiadores',
        'CALIDAD' => 'analistas_calidad',
        'ENCARGADO_PLANTA' => 'encargados_planta',
        'VENTAS' => 'personal_ventas',
    ];

    public function up(): void
    {
        Schema::table('configuraciones_pago', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('rol_id');

            $table->index(['tipo_beneficiario', 'user_id', 'estado', 'fecha_inicio'], 'idx_configuraciones_pago_trabajador_vigencia');

            $table->foreign('user_id', 'fk_configuraciones_pago_user_id')
                ->references('id')->on('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        $porRol = DB::table('configuraciones_pago')
            ->join('roles', 'roles.id', '=', 'configuraciones_pago.rol_id')
            ->where('configuraciones_pago.tipo_beneficiario', 'PERSONAL')
            ->whereNull('configuraciones_pago.user_id')
            ->where('configuraciones_pago.estado', 'ACTIVO')
            ->get(['configuraciones_pago.*', 'roles.nombre as rol_nombre']);

        foreach ($porRol as $configuracion) {
            // Rol con ficha: solo quien tiene ficha ACTIVA. Rol sin ficha (administrador): el usuario activo.
            $tabla = self::FICHA_POR_ROL[$configuracion->rol_nombre] ?? null;

            $trabajadores = DB::table('users')
                ->when($tabla !== null, fn ($query) => $query
                    ->join($tabla, "{$tabla}.user_id", '=', 'users.id')
                    ->where("{$tabla}.estado", 'ACTIVO'))
                ->where('users.role_id', $configuracion->rol_id)
                ->where('users.estado', 'ACTIVO')
                ->pluck('users.id');

            foreach ($trabajadores as $userId) {
                DB::table('configuraciones_pago')->insert([
                    'tipo_beneficiario' => 'PERSONAL',
                    'rol_id' => $configuracion->rol_id,
                    'user_id' => $userId,
                    'tipo_calculo' => $configuracion->tipo_calculo,
                    'monto_base' => $configuracion->monto_base,
                    'periodicidad' => $configuracion->periodicidad,
                    'aplica_calidad' => false,
                    'fecha_inicio' => $configuracion->fecha_inicio,
                    'fecha_fin' => $configuracion->fecha_fin,
                    'estado' => 'ACTIVO',
                    'observacion' => $configuracion->observacion,
                    'creado_por_user_id' => $configuracion->creado_por_user_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('configuraciones_pago')
            ->whereNotNull('user_id')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('obligaciones_pago')
                ->whereColumn('obligaciones_pago.configuracion_pago_id', 'configuraciones_pago.id'))
            ->delete();

        Schema::table('configuraciones_pago', function (Blueprint $table) {
            // SQLite solo sabe quitar una foreign key por sus columnas, no por su nombre.
            $table->dropForeign(DB::getDriverName() === 'sqlite' ? ['user_id'] : 'fk_configuraciones_pago_user_id');
            $table->dropIndex('idx_configuraciones_pago_trabajador_vigencia');
            $table->dropColumn('user_id');
        });
    }
};
