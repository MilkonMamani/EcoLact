<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Pago\Contracts\SueldosTrabajadoresQuery;
use App\Domain\Pago\JornadaPersonal;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Infrastructure\Persistence\Models\AcopiadorModel;
use App\Infrastructure\Persistence\Models\AnalistaCalidadModel;
use App\Infrastructure\Persistence\Models\ConfiguracionPagoModel;
use App\Infrastructure\Persistence\Models\EncargadoPlantaModel;
use App\Infrastructure\Persistence\Models\PersonalVentasModel;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Una consulta por tipo de ficha (4), una para los roles remunerables sin ficha
 * y una para todos los sueldos: sin N+1. Como en EloquentPersonalPagableQuery,
 * un trabajador es un usuario con el rol de su ficha (o el usuario mismo si su
 * rol remunerable no tiene ficha, como el administrador); su rol se muestra tal
 * cual, nunca se elige aquí.
 */
final class EloquentSueldosTrabajadoresQuery implements SueldosTrabajadoresQuery
{
    /** @var array<string, array{0: class-string<Model>, 1: string}> rol => [modelo de la ficha, etiqueta] */
    private const FICHAS = [
        'ACOPIADOR' => [AcopiadorModel::class, 'Acopiador'],
        'CALIDAD' => [AnalistaCalidadModel::class, 'Analista de calidad'],
        'ENCARGADO_PLANTA' => [EncargadoPlantaModel::class, 'Encargado de planta'],
        'VENTAS' => [PersonalVentasModel::class, 'Personal de ventas'],
    ];

    public function listar(DateTimeImmutable $hoy): array
    {
        return array_map(
            fn (array $fila): array => array_diff_key($fila, array_flip(['observacion', 'proxima', 'historial'])),
            $this->filas($hoy),
        );
    }

    public function detalle(int $userId, DateTimeImmutable $hoy): ?array
    {
        return $this->filas($hoy, $userId)[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filas(DateTimeImmutable $hoy, ?int $userId = null): array
    {
        $trabajadores = $this->trabajadores($userId);
        $sueldos = ConfiguracionPagoModel::query()
            ->where('tipo_beneficiario', TipoBeneficiarioPago::PERSONAL->value)
            ->whereIn('user_id', $trabajadores->pluck('user_id'))
            ->where('estado', 'ACTIVO')
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get()
            ->groupBy('user_id');

        $dia = $hoy->format('Y-m-d');

        return $trabajadores
            ->map(function (object $trabajador) use ($sueldos, $dia): ?array {
                /** @var Collection<int, ConfiguracionPagoModel> $vigencias */
                $vigencias = $sueldos->get($trabajador->user_id, collect());

                if (! $trabajador->activo && $vigencias->isEmpty()) {
                    return null;
                }

                $vigente = $vigencias->first(fn (ConfiguracionPagoModel $c): bool => $c->fecha_inicio->format('Y-m-d') <= $dia
                    && ($c->fecha_fin === null || $c->fecha_fin->format('Y-m-d') >= $dia));
                $proxima = $vigencias->filter(fn (ConfiguracionPagoModel $c): bool => $c->fecha_inicio->format('Y-m-d') > $dia)->last();
                $mostrada = $vigente ?? $proxima;

                return [
                    'userId' => $trabajador->user_id,
                    'nombre' => trim($trabajador->nombres.' '.$trabajador->apellidos),
                    'dni' => $trabajador->dni,
                    'rol' => $trabajador->rol,
                    'estado' => match (true) {
                        ! $trabajador->activo => 'INACTIVO',
                        $vigente !== null => 'ACTIVO',
                        $proxima !== null => 'PROGRAMADO',
                        default => 'SIN_CONFIGURAR',
                    },
                    'configuracionId' => $mostrada?->id,
                    'sueldo' => $mostrada ? (float) $mostrada->monto_base : null,
                    'valorDia' => $mostrada ? round((float) $mostrada->monto_base / JornadaPersonal::DIAS_BASE_PERIODO, 2) : null,
                    'vigenteDesde' => $mostrada?->fecha_inicio->format('Y-m-d'),
                    'observacion' => $mostrada?->observacion,
                    'proxima' => $vigente !== null && $proxima !== null ? $this->vigencia($proxima) : null,
                    'historial' => $vigencias
                        ->reject(fn (ConfiguracionPagoModel $c): bool => $c->is($vigente) || $c->is($proxima))
                        ->map(fn (ConfiguracionPagoModel $c): array => $this->vigencia($c))
                        ->values()
                        ->all(),
                ];
            })
            ->filter()
            ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Fichas de los cuatro tipos cuyo usuario tiene el rol de la ficha.
     *
     * @return Collection<int, object>
     */
    private function trabajadores(?int $userId): Collection
    {
        $todos = collect();

        foreach (self::FICHAS as $rol => [$modelo, $etiqueta]) {
            $tabla = (new $modelo)->getTable();

            $modelo::query()
                ->join('users', 'users.id', '=', "{$tabla}.user_id")
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->where('roles.nombre', $rol)
                ->when($userId !== null, fn ($query) => $query->where("{$tabla}.user_id", $userId))
                ->get(["{$tabla}.user_id", "{$tabla}.nombres", "{$tabla}.apellidos", "{$tabla}.dni", "{$tabla}.estado as estado_ficha", 'users.estado as estado_usuario'])
                ->each(fn (Model $ficha) => $todos->push((object) [
                    'user_id' => (int) $ficha->user_id,
                    'nombres' => $ficha->nombres,
                    'apellidos' => $ficha->apellidos,
                    'dni' => $ficha->dni,
                    'rol' => $etiqueta,
                    'activo' => $ficha->estado_ficha === EstadoUsuario::ACTIVO->value && $ficha->estado_usuario === EstadoUsuario::ACTIVO->value,
                ]));
        }

        // Roles remunerables SIN ficha (el administrador, o un rol nuevo): la persona es el usuario mismo.
        User::query()
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('roles.es_remunerable', true)
            ->whereNotIn('roles.nombre', array_keys(self::FICHAS))
            ->when($userId !== null, fn ($query) => $query->where('users.id', $userId))
            ->get(['users.id', 'users.username', 'users.estado', 'roles.nombre as rol_nombre'])
            ->each(fn (User $usuario) => $todos->push((object) [
                'user_id' => (int) $usuario->id,
                'nombres' => $usuario->username,
                'apellidos' => '',
                'dni' => null,
                'rol' => Str::ucfirst(Str::lower(str_replace('_', ' ', $usuario->rol_nombre))),
                'activo' => $usuario->estado === EstadoUsuario::ACTIVO->value,
            ]));

        return $todos;
    }

    /**
     * @return array{sueldo: float, desde: string, hasta: string|null}
     */
    private function vigencia(ConfiguracionPagoModel $configuracion): array
    {
        return [
            'sueldo' => (float) $configuracion->monto_base,
            'desde' => $configuracion->fecha_inicio->format('Y-m-d'),
            'hasta' => $configuracion->fecha_fin?->format('Y-m-d'),
        ];
    }
}
