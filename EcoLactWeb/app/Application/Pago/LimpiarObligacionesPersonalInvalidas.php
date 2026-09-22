<?php

namespace App\Application\Pago;

use App\Application\Pago\Contracts\PersonalPagableQuery;
use App\Domain\Auth\Repositories\RoleRepository;
use App\Domain\Pago\ObligacionPagoRepository;

/**
 * Limpia obligaciones de personal PENDIENTES generadas para usuarios que
 * nunca tuvieron un perfil laboral real (huérfanos: role_id de un rol con
 * ficha dedicada, p.ej. ACOPIADOR, pero sin registro en su tabla de ficha).
 *
 * Solo alcanza obligaciones PENDIENTES: una obligación PAGADA es historial
 * válido y nunca se toca, sin importar el estado del usuario o su ficha.
 */
final class LimpiarObligacionesPersonalInvalidas
{
    public function __construct(
        private readonly PersonalPagableQuery $personal,
        private readonly RoleRepository $roles,
        private readonly ObligacionPagoRepository $obligaciones,
    ) {}

    public function execute(): int
    {
        $total = 0;

        foreach ($this->roles->listarRemunerables() as $rol) {
            $rolId = $rol->id();
            $huerfanos = $this->personal->usuariosSinFichaReal($rolId);

            $total += $this->obligaciones->eliminarPersonalPendienteDeUsuarios($rolId, $huerfanos);
        }

        return $total;
    }
}
