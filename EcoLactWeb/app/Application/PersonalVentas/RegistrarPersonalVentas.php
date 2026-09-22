<?php

namespace App\Application\PersonalVentas;

use App\Application\Shared\TransactionManager;
use App\Domain\Auth\PasswordHasher;
use App\Domain\Auth\Repositories\RoleRepository;
use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Auth\Usuario;
use App\Domain\PersonalVentas\PersonalVentas;
use App\Domain\PersonalVentas\PersonalVentasRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use DateTimeImmutable;
use DomainException;

/**
 * Registra un nuevo miembro del personal de ventas coordinando la creación
 * de su Usuario de acceso (rol VENTAS). Sin zona: ventas opera sobre todo
 * el inventario/clientes, no una zona.
 */
final class RegistrarPersonalVentas
{
    public function __construct(
        private readonly PersonalVentasRepository $personal,
        private readonly UsuarioRepository $usuarios,
        private readonly RoleRepository $roles,
        private readonly PasswordHasher $passwordHasher,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(
        string $dni,
        string $nombres,
        string $apellidos,
        ?string $telefono,
        string $username,
        string $password,
        ?DateTimeImmutable $fechaIngreso = null,
    ): PersonalVentas {
        if ($this->personal->buscarPorDni($dni) !== null) {
            throw new DomainException("Ya existe personal de ventas registrado con el DNI {$dni}.");
        }

        if ($this->usuarios->buscarPorUsername($username) !== null) {
            throw new DomainException("Ya existe un usuario registrado con el username {$username}.");
        }

        $role = $this->roles->buscarPorNombre('VENTAS');

        if ($role === null) {
            throw new DomainException('El rol VENTAS no está configurado en el sistema.');
        }

        $roleId = $role->id() ?? throw new DomainException('El rol VENTAS no está configurado en el sistema.');

        return $this->transacciones->run(function () use (
            $dni, $nombres, $apellidos, $telefono, $username, $password, $roleId, $fechaIngreso
        ): PersonalVentas {
            $usuario = $this->usuarios->guardar(new Usuario(
                id: null,
                roleId: $roleId,
                username: $username,
                password: $this->passwordHasher->hash($password),
                estado: EstadoUsuario::ACTIVO,
                debeCambiarPassword: true,
            ));

            $userId = $usuario->id() ?? throw new DomainException('No se pudo registrar el usuario del personal de ventas.');

            return $this->personal->guardar(new PersonalVentas(
                id: null,
                userId: $userId,
                dni: $dni,
                nombres: $nombres,
                apellidos: $apellidos,
                telefono: $telefono,
                estado: EstadoUsuario::ACTIVO,
                fechaIngreso: $fechaIngreso ?? new DateTimeImmutable,
            ));
        });
    }
}
