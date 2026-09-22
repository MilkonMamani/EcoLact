<?php

namespace App\Application\EncargadoPlanta;

use App\Application\Shared\TransactionManager;
use App\Domain\Auth\PasswordHasher;
use App\Domain\Auth\Repositories\RoleRepository;
use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Auth\Usuario;
use App\Domain\EncargadoPlanta\EncargadoPlanta;
use App\Domain\EncargadoPlanta\EncargadoPlantaRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use DateTimeImmutable;
use DomainException;

/**
 * Registra un nuevo encargado de planta coordinando la creación de su
 * Usuario de acceso (rol ENCARGADO_PLANTA) y la validación de DNI/username.
 * Sin zona: el encargado de planta opera sobre toda la planta, no una zona.
 */
final class RegistrarEncargadoPlanta
{
    public function __construct(
        private readonly EncargadoPlantaRepository $encargados,
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
    ): EncargadoPlanta {
        if ($this->encargados->buscarPorDni($dni) !== null) {
            throw new DomainException("Ya existe un encargado de planta registrado con el DNI {$dni}.");
        }

        if ($this->usuarios->buscarPorUsername($username) !== null) {
            throw new DomainException("Ya existe un usuario registrado con el username {$username}.");
        }

        $role = $this->roles->buscarPorNombre('ENCARGADO_PLANTA');

        if ($role === null) {
            throw new DomainException('El rol ENCARGADO_PLANTA no está configurado en el sistema.');
        }

        $roleId = $role->id() ?? throw new DomainException('El rol ENCARGADO_PLANTA no está configurado en el sistema.');

        return $this->transacciones->run(function () use (
            $dni, $nombres, $apellidos, $telefono, $username, $password, $roleId, $fechaIngreso
        ): EncargadoPlanta {
            $usuario = $this->usuarios->guardar(new Usuario(
                id: null,
                roleId: $roleId,
                username: $username,
                password: $this->passwordHasher->hash($password),
                estado: EstadoUsuario::ACTIVO,
                debeCambiarPassword: true,
            ));

            $userId = $usuario->id() ?? throw new DomainException('No se pudo registrar el usuario del encargado de planta.');

            return $this->encargados->guardar(new EncargadoPlanta(
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
