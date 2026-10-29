<?php

namespace App\Application\Trabajador;

use App\Application\Pago\Contracts\PersonalPagableQuery;
use App\Application\Pago\PersonalPagable;
use App\Application\Pago\VentanaAsistencia;
use App\Domain\Auth\Repositories\RoleRepository;
use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Pago\JornadaPersonalRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Domain\Trabajador\Trabajador;
use App\Domain\Trabajador\TrabajadorRepository;
use DateTimeImmutable;

/**
 * Mantiene `trabajadores` sincronizada con la identidad laboral real
 * (PersonalPagableQuery, que ya filtra usuarios huérfanos sin ficha real) y
 * su historial de actividad (trabajador_periodos_actividad). Idempotente:
 * correrla muchas veces nunca duplica ni pierde histórico.
 *
 * - Alta: abre el primer periodo desde su fecha de ingreso (si ingresa hoy
 *   después del cierre de las 23:59, desde mañana).
 * - Baja: cierra el periodo. Si el día de hoy ya quedó decidido (cerró a las
 *   23:59 o ya tiene jornada) cuenta como su último día; si no, termina ayer.
 * - Reactivación: abre un periodo NUEVO desde el primer día evaluable (hoy
 *   antes de las 23:59, si no mañana). Nunca retroactivo: los días inactivos
 *   quedan fuera de todo periodo ("no correspondía").
 *
 * Los casos de uso de alta/baja/reactivación de cada ficha la llaman con
 * $soloUserId para que el cambio quede fechado el mismo día; la corrida
 * completa (nómina, falta automática) repara cualquier otro cambio.
 */
final class SincronizarTrabajadores
{
    public function __construct(
        private readonly RoleRepository $roles,
        private readonly PersonalPagableQuery $personal,
        private readonly TrabajadorRepository $trabajadores,
        private readonly UsuarioRepository $usuarios,
        private readonly JornadaPersonalRepository $jornadas,
        private readonly VentanaAsistencia $ventana,
    ) {}

    public function execute(?int $soloUserId = null): ResultadoSincronizacionTrabajadores
    {
        $resultado = new ResultadoSincronizacionTrabajadores;
        $userIdsPagables = [];

        $rolesAConsultar = $soloUserId !== null
            ? array_filter([$this->usuarios->buscarPorId($soloUserId)?->roleId()])
            : array_map(fn ($rol): ?int => $rol->id(), $this->roles->listarRemunerables());
        $remunerables = array_map(fn ($rol): ?int => $rol->id(), $this->roles->listarRemunerables());

        foreach ($rolesAConsultar as $rolId) {
            if (! in_array($rolId, $remunerables, true)) {
                continue;
            }

            foreach ($this->personal->activosPorRol($rolId) as $persona) {
                if ($soloUserId !== null && $persona->userId !== $soloUserId) {
                    continue;
                }

                $userIdsPagables[$persona->userId] = true;
                $this->asegurarActivo($persona, $resultado);
            }
        }

        $this->desactivarYaNoPagables($userIdsPagables, $soloUserId, $resultado);

        return $resultado;
    }

    private function asegurarActivo(PersonalPagable $persona, ResultadoSincronizacionTrabajadores $resultado): void
    {
        $existente = $this->trabajadores->buscarPorUserId($persona->userId);

        if ($existente === null) {
            $creado = $this->trabajadores->guardar(new Trabajador(
                id: null,
                userId: $persona->userId,
                dni: $persona->dni,
                nombres: $persona->nombre,
                apellidos: '',
                telefono: null,
                estado: EstadoUsuario::ACTIVO,
                fechaIngreso: $persona->fechaAlta,
                fechaSalida: null,
                qrToken: bin2hex(random_bytes(32)),
            ));
            $this->trabajadores->abrirPeriodo($creado->id(), $this->inicioDelIngreso($persona->fechaAlta));
            $resultado->creados++;

            return;
        }

        // Identidades creadas antes sin DNI lo toman de su ficha (la asistencia se busca por DNI).
        $completaDni = $existente->dni() === null && $persona->dni !== null;

        if (! $existente->estaActivo() || $completaDni) {
            $this->trabajadores->guardar(new Trabajador(
                id: $existente->id(),
                userId: $existente->userId(),
                dni: $existente->dni() ?? $persona->dni,
                nombres: $existente->nombres(),
                apellidos: $existente->apellidos(),
                telefono: $existente->telefono(),
                estado: EstadoUsuario::ACTIVO,
                fechaIngreso: $existente->fechaIngreso(),
                fechaSalida: $existente->estaActivo() ? $existente->fechaSalida() : null,
                qrToken: $existente->qrToken() ?? bin2hex(random_bytes(32)),
            ));
        }

        if (! $existente->estaActivo()) {
            $this->trabajadores->abrirPeriodo($existente->id(), $this->ventana->primerDiaEvaluable());
            $resultado->reactivados++;
        } elseif (! $existente->tienePeriodosRegistrados()) {
            $this->trabajadores->abrirPeriodo($existente->id(), $existente->fechaIngreso());
        }
    }

    /**
     * @param  array<int, true>  $userIdsPagables
     */
    private function desactivarYaNoPagables(array $userIdsPagables, ?int $soloUserId, ResultadoSincronizacionTrabajadores $resultado): void
    {
        $candidatos = $soloUserId !== null
            ? array_filter([$this->trabajadores->buscarPorUserId($soloUserId)], fn (?Trabajador $trabajador): bool => $trabajador?->estaActivo() ?? false)
            : $this->trabajadores->listarActivos();

        foreach ($candidatos as $trabajador) {
            if (isset($userIdsPagables[$trabajador->userId()])) {
                continue;
            }

            $ultimoDia = $this->ultimoDiaActivo($trabajador);

            $this->trabajadores->guardar(new Trabajador(
                id: $trabajador->id(),
                userId: $trabajador->userId(),
                dni: $trabajador->dni(),
                nombres: $trabajador->nombres(),
                apellidos: $trabajador->apellidos(),
                telefono: $trabajador->telefono(),
                estado: EstadoUsuario::INACTIVO,
                fechaIngreso: $trabajador->fechaIngreso(),
                fechaSalida: max($ultimoDia, $trabajador->fechaIngreso()),
                qrToken: $trabajador->qrToken(),
            ));
            $this->trabajadores->cerrarPeriodo($trabajador->id(), $ultimoDia);
            $resultado->desactivados++;
        }
    }

    /** Ingreso hoy (o en el futuro) empieza a contar desde el primer día evaluable; un ingreso pasado se respeta tal cual. */
    private function inicioDelIngreso(DateTimeImmutable $fechaAlta): DateTimeImmutable
    {
        $hoy = $this->ventana->hoy();

        return $fechaAlta->setTime(0, 0) < $hoy ? $fechaAlta : max($fechaAlta->setTime(0, 0), $this->ventana->primerDiaEvaluable());
    }

    /** Hoy cuenta como último día si su asistencia ya cerró o ya tiene jornada; si sigue abierta y sin marcar, termina ayer. */
    private function ultimoDiaActivo(Trabajador $trabajador): DateTimeImmutable
    {
        $hoy = $this->ventana->hoy();

        return $this->ventana->estaAbierta($hoy) && ! $this->jornadas->existeEnFecha($trabajador->id(), $hoy)
            ? $hoy->modify('-1 day')
            : $hoy;
    }
}
