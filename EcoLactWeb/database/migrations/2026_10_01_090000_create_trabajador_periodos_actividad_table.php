<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historial temporal de actividad de cada trabajador: un tramo [fecha_inicio,
 * fecha_fin] por cada periodo en que estuvo ACTIVO (fecha_fin NULL = sigue
 * activo). `trabajadores` solo guardaba fecha_ingreso/fecha_salida (un único
 * intervalo) y la reactivación borraba fecha_salida, así que se perdía el
 * periodo inactivo y la asistencia/nómina lo trataban como días laborables.
 *
 * Backfill con la mejor fuente real disponible:
 * - Acopiadores: acopiador_zona_historial (se cierra al desactivar y se abre al
 *   reactivar), fusionando tramos contiguos (un cambio de zona no es inactividad).
 * - Resto de roles (sin historial previo): un único tramo desde fecha_ingreso
 *   hasta fecha_salida (o abierto si está ACTIVO).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trabajador_periodos_actividad', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->charset('utf8mb4');
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('trabajador_id');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['trabajador_id', 'fecha_inicio'], 'idx_trabajador_periodos_trabajador_inicio');

            $table->foreign('trabajador_id', 'fk_trabajador_periodos_trabajador_id')
                ->references('id')->on('trabajadores')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        $ahora = now();

        foreach (DB::table('trabajadores')->orderBy('id')->get() as $trabajador) {
            foreach ($this->periodosDe($trabajador) as [$inicio, $fin]) {
                DB::table('trabajador_periodos_actividad')->insert([
                    'trabajador_id' => $trabajador->id,
                    'fecha_inicio' => $inicio,
                    'fecha_fin' => $fin,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trabajador_periodos_actividad');
    }

    /** @return list<array{0: string, 1: ?string}> */
    private function periodosDe(object $trabajador): array
    {
        $activo = $trabajador->estado === 'ACTIVO';
        $ingreso = substr((string) $trabajador->fecha_ingreso, 0, 10);
        $salida = $trabajador->fecha_salida !== null ? substr((string) $trabajador->fecha_salida, 0, 10) : null;
        $finInactivo = $salida ?? substr((string) ($trabajador->updated_at ?? $ingreso), 0, 10);

        $tramos = DB::table('acopiador_zona_historial')
            ->join('acopiadores', 'acopiadores.id', '=', 'acopiador_zona_historial.acopiador_id')
            ->where('acopiadores.user_id', $trabajador->user_id)
            ->orderBy('acopiador_zona_historial.fecha_inicio')
            ->orderBy('acopiador_zona_historial.id')
            ->get(['acopiador_zona_historial.fecha_inicio', 'acopiador_zona_historial.fecha_fin']);

        if ($tramos->isEmpty()) {
            return [[$ingreso, $activo ? null : max($ingreso, $finInactivo)]];
        }

        $periodos = [];

        foreach ($tramos as $tramo) {
            $inicio = max($ingreso, substr((string) $tramo->fecha_inicio, 0, 10));
            $fin = $tramo->fecha_fin !== null ? max($inicio, substr((string) $tramo->fecha_fin, 0, 10)) : null;
            $ultimo = array_key_last($periodos);

            // Contiguo o solapado con el anterior (p. ej. cambio de zona el mismo día): es el mismo periodo activo.
            if ($ultimo !== null && ($periodos[$ultimo][1] === null || $inicio <= date('Y-m-d', strtotime($periodos[$ultimo][1].' +1 day')))) {
                $periodos[$ultimo][1] = $periodos[$ultimo][1] === null || $fin === null ? null : max($periodos[$ultimo][1], $fin);

                continue;
            }

            $periodos[] = [$inicio, $fin];
        }

        $ultimo = array_key_last($periodos);

        // El estado actual manda sobre el último tramo: activo => abierto; inactivo => cerrado.
        if ($activo) {
            $periodos[$ultimo][1] = null;
        } elseif ($periodos[$ultimo][1] === null) {
            $periodos[$ultimo][1] = max($periodos[$ultimo][0], $finInactivo);
        }

        return $periodos;
    }
};
