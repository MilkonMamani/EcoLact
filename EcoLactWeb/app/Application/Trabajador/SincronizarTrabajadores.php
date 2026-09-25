<?php

namespace App\Application\Trabajador;

use App\Application\Pago\Contracts\PersonalPagableQuery;
use App\Application\Pago\PersonalPagable;
use App\Domain\Auth\Repositories\RoleRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Domain\Trabajador\Trabajador;
use App\Domain\Trabajador\TrabajadorRepository;
use DateTimeImmutable;

/**
 * Mantiene `trabajadores` sincronizada con la identidad laboral real
 * (PersonalPagableQuery, que ya filtra usuarios huérfanos sin ficha real).
 * Idempotente: correrla muchas veces nunca duplica ni pierde histórico. Es
 * el primer paso de GenerarObligacionesPersonal y de MarcarFaltasAutomaticas,
 * para que un trabajador nuevo (o uno que dejó de ser pagable) quede
 * reflejado el mismo día, sin esperar al cierre de mes.
 */
final class SincronizarTrabajadores
{
    public function __construct(
        private readonly RoleRepository $roles,
        private readonly PersonalPagableQuery $personal,
        private readonly TrabajadorRepository $trabajadores,
    ) {}

    public function execute(?DateTimeImmutable $hoy = null): ResultadoSincronizacionTrabajadores
    {
        $hoy = ($hoy ?? DateTimeImmutable::createFromInterface(now()))->setTime(0, 0);
        $resultado = new ResultadoSincronizacionTrabajadores;
        $userIdsPagables = [];

        foreach ($this->roles->listarRemunerables() as $rol) {
            foreach ($this->personal->activosPorRol($rol->id()) as $persona) {
                $userIdsPagables[$persona->userId] = true;
                $this->asegurarActivo($persona, $resultado);
            }
        }

        $this->desactivarYaNoPagables($userIdsPagables, $hoy, $resultado);

        return $resultado;
    }

    private function asegurarActivo(PersonalPagable $persona, ResultadoSincronizacionTrabajadores $resultado): void
    {
        $existente = $this->trabajadores->buscarPorUserId($persona->userId);

        if ($existente === null) {
            $this->trabajadores->guardar(new Trabajador(
                id: null,
                userId: $persona->userId,
                dni: null,
                nombres: $persona->nombre,
                apellidos: '',
                telefono: null,
                estado: EstadoUsuario::ACTIVO,
                fechaIngreso: $persona->fechaAlta,
                fechaSalida: null,
                qrToken: bin2hex(random_bytes(32)),
            ));
            $resultado->creados++;

            return;
        }

        if (! $existente->estaActivo()) {
            $this->trabajadores->guardar(new Trabajador(
                id: $existente->id(),
                userId: $existente->userId(),
                dni: $existente->dni(),
                nombres: $existente->nombres(),
                apellidos: $existente->apellidos(),
                telefono: $existente->telefono(),
                estado: EstadoUsuario::ACTIVO,
                fechaIngreso: $existente->fechaIngreso(),
                fechaSalida: null,
                qrToken: $existente->qrToken() ?? bin2hex(random_bytes(32)),
            ));
            $resultado->reactivados++;
        }
    }

    /**
     * @param  array<int, true>  $userIdsPagables
     */
    private function desactivarYaNoPagables(array $userIdsPagables, DateTimeImmutable $hoy, ResultadoSincronizacionTrabajadores $resultado): void
    {
        foreach ($this->trabajadores->listarActivos() as $trabajador) {
            if (isset($userIdsPagables[$trabajador->userId()]) || $trabajador->fechaSalida() !== null) {
                continue;
            }

            $this->trabajadores->guardar(new Trabajador(
                id: $trabajador->id(),
                userId: $trabajador->userId(),
                dni: $trabajador->dni(),
                nombres: $trabajador->nombres(),
                apellidos: $trabajador->apellidos(),
                telefono: $trabajador->telefono(),
                estado: EstadoUsuario::INACTIVO,
                fechaIngreso: $trabajador->fechaIngreso(),
                fechaSalida: $hoy,
                qrToken: $trabajador->qrToken(),
            ));
            $resultado->desactivados++;
        }
    }
}
