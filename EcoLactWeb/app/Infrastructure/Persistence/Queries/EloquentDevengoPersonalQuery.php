<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Pago\Contracts\DevengoPersonalQuery;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentDevengoPersonalQuery implements DevengoPersonalQuery
{
    public function roles(): array
    {
        return DB::table('roles')->get(['id', 'nombre', 'es_remunerable', 'controla_asistencia'])
            ->mapWithKeys(fn (object $rol): array => [(int) $rol->id => [
                'nombre' => $rol->nombre,
                'remunerable' => (bool) $rol->es_remunerable,
                'controlaAsistencia' => (bool) $rol->controla_asistencia,
            ]])
            ->all();
    }

    public function rolActualPorUsuario(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return DB::table('users')->whereIn('id', $userIds)->pluck('role_id', 'id')
            ->map(fn (mixed $rolId): int => (int) $rolId)
            ->all();
    }

    public function jornadasEntre(DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        $jornadas = [];

        DB::table('jornadas_personal')
            ->whereDate('fecha', '>=', $desde->format('Y-m-d'))
            ->whereDate('fecha', '<=', $hasta->format('Y-m-d'))
            ->orderBy('id')
            ->get(['trabajador_id', 'rol_id', 'fecha', 'estado'])
            ->each(function (object $jornada) use (&$jornadas): void {
                $jornadas[(int) $jornada->trabajador_id][substr((string) $jornada->fecha, 0, 10)] = [
                    'estado' => $jornada->estado,
                    'rolId' => (int) $jornada->rol_id,
                ];
            });

        return $jornadas;
    }

    public function obligacionesMensuales(DateTimeImmutable $desde, DateTimeImmutable $hasta): array
    {
        $obligaciones = [];

        DB::table('obligaciones_pago')
            ->where('tipo_beneficiario', TipoBeneficiarioPago::PERSONAL->value)
            ->whereNotNull('user_id')
            ->whereDate('periodo_inicio', '>=', $desde->format('Y-m-01'))
            ->whereDate('periodo_inicio', '<=', $hasta->format('Y-m-d'))
            ->orderBy('id')
            ->get(['id', 'user_id', 'periodo_inicio', 'estado', 'acumulado', 'monto_calculado', 'base_calculo', 'fecha_pago_programada'])
            ->each(function (object $obligacion) use (&$obligaciones): void {
                $base = json_decode((string) $obligacion->base_calculo, true) ?: [];

                $obligaciones[(int) $obligacion->user_id][substr((string) $obligacion->periodo_inicio, 0, 7)] = [
                    'id' => (int) $obligacion->id,
                    'estado' => $obligacion->estado,
                    'acumulado' => (float) ($obligacion->acumulado ?? $obligacion->monto_calculado),
                    'montoCalculado' => (float) $obligacion->monto_calculado,
                    'sueldoBase' => isset($base['sueldo_base']) ? (float) $base['sueldo_base'] : null,
                    'fechaPago' => $obligacion->fecha_pago_programada !== null ? substr((string) $obligacion->fecha_pago_programada, 0, 10) : null,
                ];
            });

        return $obligaciones;
    }
}
