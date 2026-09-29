<?php

namespace App\Application\Trabajador;

use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Auth\Usuario;
use App\Domain\Shared\Enums\EstadoUsuario;

/**
 * La cuenta de usuario (users.estado) sigue el estado de la ficha del trabajador
 * (acopiador, analista de calidad, encargado de planta, personal de ventas):
 * desactivar al trabajador desactiva también su usuario, y reactivarlo lo reactiva.
 * Lo llaman los casos de uso Desactivar/Reactivar de cada ficha.
 */
final class CambiarEstadoCuentaTrabajador
{
    public function __construct(private readonly UsuarioRepository $usuarios) {}

    public function execute(int $userId, EstadoUsuario $estado): void
    {
        $usuario = $this->usuarios->buscarPorId($userId);

        if ($usuario === null || $usuario->estado() === $estado) {
            return;
        }

        $this->usuarios->actualizar(new Usuario(
            id: $usuario->id(),
            roleId: $usuario->roleId(),
            username: $usuario->username(),
            password: $usuario->password(),
            estado: $estado,
            debeCambiarPassword: $usuario->debeCambiarPassword(),
            ultimoAcceso: $usuario->ultimoAcceso(),
        ));
    }
}
