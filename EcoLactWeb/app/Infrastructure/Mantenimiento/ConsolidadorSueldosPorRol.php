<?php

namespace App\Infrastructure\Mantenimiento;

use App\Application\Pago\Contracts\PersonalPagableQuery;
use App\Application\Pago\RegistrarConfiguracionPago;
use App\Domain\Auth\Role;
use App\Domain\Shared\Enums\TipoBeneficiarioPago;
use App\Infrastructure\Persistence\Models\ConfiguracionPagoModel;
use App\Infrastructure\Persistence\Models\RoleModel;
use DateTimeImmutable;
use Illuminate\Support\Collection;

/**
 * Pasa los sueldos individuales (modelo por trabajador, oct. 2026) a un sueldo base POR ROL.
 * Ver el comando `pagos:consolidar-sueldos-por-rol`.
 *
 * Por cada rol remunerable:
 *  - Si el rol ya tiene un sueldo por rol vigente o programado, no se toca (YA_CONFIGURADO).
 *  - Se toma el sueldo individual ACTIVO vigente hoy (o el próximo programado) de cada
 *    trabajador activo del rol. Sin ninguno: SIN_DATOS (queda pendiente en la pantalla).
 *  - Si todos coinciden en el monto: PROPUESTO, con ese monto y la fecha de inicio más
 *    antigua entre ellos. Los trabajadores sin sueldo individual lo heredan.
 *  - Si hay montos distintos: CONFLICTO. Nunca se elige uno automáticamente; el
 *    administrador decide en Configuración de pagos (el panel del rol muestra los valores).
 *
 * aplicar() solo crea los sueldos PROPUESTOS, con RegistrarConfiguracionPago (misma
 * validación de solapes y auditoría que la pantalla). Las filas individuales NO se modifican
 * ni se borran: son historial y las obligaciones ya generadas siguen apuntando a ellas.
 */
final class ConsolidadorSueldosPorRol
{
    public function __construct(
        private readonly PersonalPagableQuery $personal,
        private readonly RegistrarConfiguracionPago $registrar,
    ) {}

    /**
     * @return list<array{rolId: int, rol: string, estado: string, sueldo: float|null, desde: string|null, creadoPor: int|null, individuales: list<array{nombre: string, sueldo: float, desde: string}>, heredan: list<string>}>
     */
    public function planificar(DateTimeImmutable $hoy): array
    {
        $dia = $hoy->format('Y-m-d');

        return RoleModel::query()
            ->where('es_remunerable', true)
            ->orderBy('id')
            ->get(['id', 'nombre'])
            ->map(function (RoleModel $rol) use ($dia): array {
                $trabajadores = collect($this->personal->activosPorRol($rol->id))->keyBy('userId');
                $individuales = $this->individualesVigentes($trabajadores->keys()->all(), $dia);
                $montos = $individuales->pluck('monto_base')->map(fn ($monto): string => number_format((float) $monto, 2, '.', ''))->unique();

                $estado = match (true) {
                    $this->rolConfigurado($rol->id, $dia) => 'YA_CONFIGURADO',
                    $individuales->isEmpty() => 'SIN_DATOS',
                    $montos->count() > 1 => 'CONFLICTO',
                    default => 'PROPUESTO',
                };
                $base = $estado === 'PROPUESTO' ? $individuales->sortBy(fn (ConfiguracionPagoModel $c): string => $c->fecha_inicio->format('Y-m-d'))->first() : null;

                return [
                    'rolId' => $rol->id,
                    'rol' => Role::etiquetaDe($rol->nombre),
                    'estado' => $estado,
                    'sueldo' => $base ? (float) $base->monto_base : null,
                    'desde' => $base?->fecha_inicio->format('Y-m-d'),
                    'creadoPor' => $base?->creado_por_user_id,
                    'individuales' => $individuales
                        ->map(fn (ConfiguracionPagoModel $c): array => [
                            'nombre' => $trabajadores[$c->user_id]->nombre,
                            'sueldo' => (float) $c->monto_base,
                            'desde' => $c->fecha_inicio->format('Y-m-d'),
                        ])
                        ->values()
                        ->all(),
                    'heredan' => $trabajadores
                        ->reject(fn ($persona): bool => $individuales->contains('user_id', $persona->userId))
                        ->pluck('nombre')
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    /**
     * Crea los sueldos por rol PROPUESTOS. Devuelve cuántos creó.
     */
    public function aplicar(DateTimeImmutable $hoy): int
    {
        $creados = 0;

        foreach ($this->planificar($hoy) as $paso) {
            if ($paso['estado'] !== 'PROPUESTO') {
                continue;
            }

            $this->registrar->execute(
                tipo: TipoBeneficiarioPago::PERSONAL,
                rolId: $paso['rolId'],
                montoBase: $paso['sueldo'],
                aplicaCalidad: false,
                fechaInicio: new DateTimeImmutable($paso['desde']),
                observacion: 'Consolidado desde los sueldos individuales del modelo anterior.',
                creadoPorUserId: $paso['creadoPor'],
            );
            $creados++;
        }

        return $creados;
    }

    private function rolConfigurado(int $rolId, string $dia): bool
    {
        return ConfiguracionPagoModel::query()
            ->where('tipo_beneficiario', TipoBeneficiarioPago::PERSONAL->value)
            ->where('rol_id', $rolId)
            ->whereNull('user_id')
            ->where('estado', 'ACTIVO')
            ->where(fn ($query) => $query->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $dia))
            ->exists();
    }

    /**
     * Por trabajador, su sueldo individual ACTIVO vigente hoy o, si no, el próximo programado.
     *
     * @param  list<int>  $userIds
     * @return Collection<int, ConfiguracionPagoModel>
     */
    private function individualesVigentes(array $userIds, string $dia): Collection
    {
        return ConfiguracionPagoModel::query()
            ->where('tipo_beneficiario', TipoBeneficiarioPago::PERSONAL->value)
            ->whereIn('user_id', $userIds)
            ->where('estado', 'ACTIVO')
            ->where(fn ($query) => $query->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $dia))
            ->orderBy('fecha_inicio')
            ->get()
            ->unique('user_id')
            ->values();
    }
}
