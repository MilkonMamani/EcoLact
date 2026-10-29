<?php

namespace App\Infrastructure\Persistence\Queries;

use App\Application\Pago\Contracts\PersonalPagableQuery;
use App\Application\Pago\Contracts\SueldosPorRolQuery;
use App\Application\Pago\PersonalPagable;
use App\Domain\Auth\Role;
use App\Domain\Pago\JornadaPersonal;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Infrastructure\Persistence\Models\ConfiguracionPagoModel;
use App\Infrastructure\Persistence\Models\RoleModel;
use DateTimeImmutable;
use Illuminate\Support\Collection;

/**
 * Una consulta para los roles remunerables, una para todos sus sueldos (solo
 * filas por rol: user_id nulo) y, por rol, el personal activo de
 * PersonalPagableQuery: el mismo criterio con el que Pagos genera los sueldos,
 * así "Personal asignado" es exactamente quién cobra.
 */
final class EloquentSueldosPorRolQuery implements SueldosPorRolQuery
{
    public function __construct(private readonly PersonalPagableQuery $personal) {}

    public function listar(DateTimeImmutable $hoy): array
    {
        $roles = array_map(
            fn (array $fila): array => array_diff_key($fila, array_flip(['observacion', 'proxima', 'historial', 'trabajadores'])),
            $this->filas($hoy),
        );
        $configurados = count(array_filter($roles, fn (array $fila): bool => $fila['estado'] === 'ACTIVO'));

        return [
            'roles' => $roles,
            'resumen' => ['roles' => count($roles), 'configurados' => $configurados, 'pendientes' => count($roles) - $configurados],
        ];
    }

    public function detalle(int $rolId, DateTimeImmutable $hoy): ?array
    {
        $fila = $this->filas($hoy, $rolId)[0] ?? null;

        if ($fila === null) {
            return null;
        }

        $fila['sueldosIndividualesAnteriores'] = $this->sueldosIndividualesAnteriores($fila['trabajadores']);

        return $fila;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function filas(DateTimeImmutable $hoy, ?int $rolId = null): array
    {
        $roles = RoleModel::query()
            ->where('es_remunerable', true)
            ->when($rolId !== null, fn ($query) => $query->whereKey($rolId))
            ->get(['id', 'nombre']);

        $sueldos = ConfiguracionPagoModel::query()
            ->with('creadoPor:id,username')
            ->where('tipo_beneficiario', TipoBeneficiarioPago::PERSONAL->value)
            ->whereNull('user_id')
            ->whereIn('rol_id', $roles->pluck('id'))
            ->where('estado', 'ACTIVO')
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get()
            ->groupBy('rol_id');

        $dia = $hoy->format('Y-m-d');

        return $roles
            ->map(function (RoleModel $rol) use ($sueldos, $dia): array {
                /** @var Collection<int, ConfiguracionPagoModel> $vigencias */
                $vigencias = $sueldos->get($rol->id, collect());
                $vigente = $vigencias->first(fn (ConfiguracionPagoModel $c): bool => $c->fecha_inicio->format('Y-m-d') <= $dia
                    && ($c->fecha_fin === null || $c->fecha_fin->format('Y-m-d') >= $dia));
                $proxima = $vigencias->filter(fn (ConfiguracionPagoModel $c): bool => $c->fecha_inicio->format('Y-m-d') > $dia)->last();
                $mostrada = $vigente ?? $proxima;
                $trabajadores = $this->personal->activosPorRol($rol->id);
                usort($trabajadores, fn (PersonalPagable $a, PersonalPagable $b): int => strnatcasecmp($a->nombre, $b->nombre));

                return [
                    'rolId' => $rol->id,
                    'clave' => $rol->nombre,
                    'rol' => Role::etiquetaDe($rol->nombre),
                    'personal' => count($trabajadores),
                    'estado' => match (true) {
                        $vigente !== null => 'ACTIVO',
                        $proxima !== null => 'PROGRAMADO',
                        default => 'SIN_CONFIGURAR',
                    },
                    'configuracionId' => $mostrada?->id,
                    'sueldo' => $mostrada ? (float) $mostrada->monto_base : null,
                    'valorDia' => $mostrada ? round((float) $mostrada->monto_base / JornadaPersonal::DIAS_BASE_PERIODO, 2) : null,
                    'vigenteDesde' => $mostrada?->fecha_inicio->format('Y-m-d'),
                    'observacion' => $mostrada?->observacion,
                    'proxima' => $vigente !== null && $proxima !== null ? $this->vigencia($proxima, 'PROGRAMADO') : null,
                    'historial' => $vigencias
                        ->map(fn (ConfiguracionPagoModel $c): array => $this->vigencia($c, match (true) {
                            $c->is($vigente) => 'VIGENTE',
                            $c->fecha_inicio->format('Y-m-d') > $dia => 'PROGRAMADO',
                            default => 'ANTERIOR',
                        }))
                        ->values()
                        ->all(),
                    'trabajadores' => array_map(
                        fn (PersonalPagable $persona): array => ['userId' => $persona->userId, 'nombre' => $persona->nombre, 'dni' => $persona->dni],
                        $trabajadores,
                    ),
                ];
            })
            ->sortBy('rol', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Último sueldo individual ACTIVO que cada trabajador tenía antes de que el
     * sueldo pasara a ser por rol. Solo referencia: sirve para decidir el sueldo
     * del rol cuando sus trabajadores tenían valores distintos.
     *
     * @param  list<array{userId: int, nombre: string, dni: string|null}>  $trabajadores
     * @return list<array{nombre: string, sueldo: float, desde: string}>
     */
    private function sueldosIndividualesAnteriores(array $trabajadores): array
    {
        $nombres = array_column($trabajadores, 'nombre', 'userId');

        return ConfiguracionPagoModel::query()
            ->where('tipo_beneficiario', TipoBeneficiarioPago::PERSONAL->value)
            ->whereIn('user_id', array_keys($nombres))
            ->where('estado', 'ACTIVO')
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get()
            ->unique('user_id')
            ->map(fn (ConfiguracionPagoModel $c): array => [
                'nombre' => $nombres[$c->user_id],
                'sueldo' => (float) $c->monto_base,
                'desde' => $c->fecha_inicio->format('Y-m-d'),
            ])
            ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * @return array{sueldo: float, desde: string, hasta: string|null, estado: string, registradoPor: string|null, observacion: string|null}
     */
    private function vigencia(ConfiguracionPagoModel $configuracion, string $estado): array
    {
        return [
            'sueldo' => (float) $configuracion->monto_base,
            'desde' => $configuracion->fecha_inicio->format('Y-m-d'),
            'hasta' => $configuracion->fecha_fin?->format('Y-m-d'),
            'estado' => $estado,
            'registradoPor' => $configuracion->creadoPor?->username,
            'observacion' => $configuracion->observacion,
        ];
    }
}
