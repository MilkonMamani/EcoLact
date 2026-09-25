<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corte de modelo (mes calendario en vez de ventana móvil de 30 días), en
 * cuatro pasos, sin down() porque no es reversible sin fabricar historial
 * falso (mismo criterio que 2026_09_28_100002):
 *
 * 1) Puebla `trabajadores` replicando EN SQL el mismo criterio de
 *    EloquentPersonalPagableQuery::activosPorRol: usuario ACTIVO con un rol
 *    remunerable y, si el rol tiene ficha dedicada (acopiadores,
 *    analistas_calidad, encargados_planta, personal_ventas), una ficha real
 *    y ACTIVA; ADMINISTRADOR (sin ficha) usa el username como nombre. Así
 *    nunca se crea un trabajador para una cuenta huérfana (p.ej. role_id de
 *    ACOPIADOR sin fila en `acopiadores`).
 * 2) Backfill de jornadas_personal.trabajador_id por user_id.
 * 3) Borra jornadas_personal huérfanas (su user_id nunca tuvo trabajador
 *    real: datos de desarrollo inválidos).
 * 4) Borra las obligaciones_pago PERSONAL en EN_CURSO del modelo viejo
 *    (ventana móvil de 30 días, periodos que no calzan con mes calendario).
 *    Las PENDIENTES de ese modelo ya se habían borrado en
 *    2026_09_28_100002. Las PAGADAS (histórico financiero real) nunca se
 *    tocan. El generador corregido (GenerarObligacionesPersonal, vía
 *    SincronizarTrabajadores) recrea las EN_CURSO correctas del mes
 *    calendario actual la próxima vez que corra.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->poblarTrabajadores();
        $this->backfillTrabajadorIdEnJornadas();
        $this->borrarJornadasHuerfanas();

        DB::table('obligaciones_pago')
            ->where('tipo_beneficiario', 'PERSONAL')
            ->where('estado', 'EN_CURSO')
            ->delete();
    }

    public function down(): void
    {
        // Intencional: no se puede reconstruir el estado anterior sin fabricar historial falso.
    }

    private function poblarTrabajadores(): void
    {
        $fichaPorRol = [
            'ACOPIADOR' => 'acopiadores',
            'CALIDAD' => 'analistas_calidad',
            'ENCARGADO_PLANTA' => 'encargados_planta',
            'VENTAS' => 'personal_ventas',
        ];

        $roles = DB::table('roles')->where('es_remunerable', true)->get(['id', 'nombre']);
        $ahora = now();

        foreach ($roles as $rol) {
            $tablaFicha = $fichaPorRol[$rol->nombre] ?? null;

            if ($tablaFicha === null) {
                $usuarios = DB::table('users')->where('role_id', $rol->id)->where('estado', 'ACTIVO')->get();

                foreach ($usuarios as $usuario) {
                    $this->insertarTrabajador(
                        userId: $usuario->id,
                        dni: null,
                        nombres: $usuario->username,
                        apellidos: '',
                        telefono: null,
                        fechaIngreso: $usuario->created_at,
                        ahora: $ahora,
                    );
                }

                continue;
            }

            $filas = DB::table('users')
                ->join($tablaFicha, "{$tablaFicha}.user_id", '=', 'users.id')
                ->where('users.role_id', $rol->id)
                ->where('users.estado', 'ACTIVO')
                ->where("{$tablaFicha}.estado", 'ACTIVO')
                ->get([
                    'users.id as user_id',
                    "{$tablaFicha}.dni as dni",
                    "{$tablaFicha}.nombres as nombres",
                    "{$tablaFicha}.apellidos as apellidos",
                    "{$tablaFicha}.telefono as telefono",
                    "{$tablaFicha}.fecha_ingreso as fecha_ingreso",
                    "{$tablaFicha}.created_at as ficha_created_at",
                ]);

            foreach ($filas as $fila) {
                $this->insertarTrabajador(
                    userId: $fila->user_id,
                    dni: $fila->dni,
                    nombres: $fila->nombres,
                    apellidos: $fila->apellidos,
                    telefono: $fila->telefono,
                    fechaIngreso: $fila->fecha_ingreso ?? $fila->ficha_created_at,
                    ahora: $ahora,
                );
            }
        }
    }

    private function insertarTrabajador(
        int $userId,
        ?string $dni,
        string $nombres,
        string $apellidos,
        ?string $telefono,
        string $fechaIngreso,
        $ahora,
    ): void {
        if (DB::table('trabajadores')->where('user_id', $userId)->exists()) {
            return;
        }

        DB::table('trabajadores')->insert([
            'user_id' => $userId,
            'dni' => $dni,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'telefono' => $telefono,
            'estado' => 'ACTIVO',
            'fecha_ingreso' => $fechaIngreso,
            'fecha_salida' => null,
            'qr_token' => bin2hex(random_bytes(32)),
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    /** Portable entre MySQL (producción) y SQLite (tests): sin UPDATE...JOIN. */
    private function backfillTrabajadorIdEnJornadas(): void
    {
        $idsTrabajadorPorUserId = DB::table('trabajadores')->pluck('id', 'user_id');

        DB::table('jornadas_personal')->whereNull('trabajador_id')->orderBy('id')->select(['id', 'user_id'])
            ->get()
            ->each(function ($jornada) use ($idsTrabajadorPorUserId): void {
                $trabajadorId = $idsTrabajadorPorUserId->get($jornada->user_id);

                if ($trabajadorId !== null) {
                    DB::table('jornadas_personal')->where('id', $jornada->id)->update(['trabajador_id' => $trabajadorId]);
                }
            });
    }

    private function borrarJornadasHuerfanas(): void
    {
        DB::table('jornadas_personal')->whereNull('trabajador_id')->delete();
    }
};
