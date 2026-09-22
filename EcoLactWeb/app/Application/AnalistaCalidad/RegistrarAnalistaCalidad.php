<?php

namespace App\Application\AnalistaCalidad;

use App\Application\Shared\TransactionManager;
use App\Domain\AnalistaCalidad\AnalistaCalidad;
use App\Domain\AnalistaCalidad\AnalistaCalidadRepository;
use App\Domain\Auth\PasswordHasher;
use App\Domain\Auth\Repositories\RoleRepository;
use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Auth\Usuario;
use App\Domain\Shared\Enums\EstadoUsuario;
use DateTimeImmutable;
use DomainException;

/**
 * Registra un nuevo analista de calidad coordinando la creación de su
 * Usuario de acceso (rol CALIDAD). Sin zona: el analista puede hacer
 * control de calidad en cualquier zona.
 */
final class RegistrarAnalistaCalidad
{
    public function __construct(
        private readonly AnalistaCalidadRepository $analistas,
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
    ): AnalistaCalidad {
        if ($this->analistas->buscarPorDni($dni) !== null) {
            throw new DomainException("Ya existe un analista de calidad registrado con el DNI {$dni}.");
        }

        if ($this->usuarios->buscarPorUsername($username) !== null) {
            throw new DomainException("Ya existe un usuario registrado con el username {$username}.");
        }

        $role = $this->roles->buscarPorNombre('CALIDAD');

        if ($role === null) {
            throw new DomainException('El rol CALIDAD no está configurado en el sistema.');
        }

        $roleId = $role->id() ?? throw new DomainException('El rol CALIDAD no está configurado en el sistema.');

        return $this->transacciones->run(function () use (
            $dni, $nombres, $apellidos, $telefono, $username, $password, $roleId, $fechaIngreso
        ): AnalistaCalidad {
            $usuario = $this->usuarios->guardar(new Usuario(
                id: null,
                roleId: $roleId,
                username: $username,
                password: $this->passwordHasher->hash($password),
                estado: EstadoUsuario::ACTIVO,
                debeCambiarPassword: true,
            ));

            $userId = $usuario->id() ?? throw new DomainException('No se pudo registrar el usuario del analista de calidad.');

            return $this->analistas->guardar(new AnalistaCalidad(
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
