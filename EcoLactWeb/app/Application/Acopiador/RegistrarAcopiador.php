<?php

namespace App\Application\Acopiador;

use App\Application\Shared\TransactionManager;
use App\Domain\Acopiador\Acopiador;
use App\Domain\Acopiador\AcopiadorRepository;
use App\Domain\Acopiador\AcopiadorZonaHistorial;
use App\Domain\Acopiador\AcopiadorZonaHistorialRepository;
use App\Domain\Auth\PasswordHasher;
use App\Domain\Auth\Repositories\RoleRepository;
use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Auth\Usuario;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Domain\Zona\ZonaRepository;
use DateTimeImmutable;
use DomainException;

/**
 * Registra un nuevo acopiador coordinando la creación de su Usuario de
 * acceso, la validación de DNI/zona y la asignación inicial de zona (con
 * historial). Simétrico a RegistrarProveedor.
 */
final class RegistrarAcopiador
{
    public function __construct(
        private readonly AcopiadorRepository $acopiadores,
        private readonly UsuarioRepository $usuarios,
        private readonly ZonaRepository $zonas,
        private readonly AcopiadorZonaHistorialRepository $zonaHistorial,
        private readonly RoleRepository $roles,
        private readonly PasswordHasher $passwordHasher,
        private readonly TransactionManager $transacciones,
    ) {}

    public function execute(
        string $dni,
        string $nombres,
        string $apellidos,
        ?string $telefono,
        int $zonaId,
        string $username,
        string $password,
        ?DateTimeImmutable $fechaIngreso = null,
    ): Acopiador {
        if ($this->acopiadores->buscarPorDni($dni) !== null) {
            throw new DomainException("Ya existe un acopiador registrado con el DNI {$dni}.");
        }

        $zona = $this->zonas->buscarPorId($zonaId);

        if ($zona === null || ! $zona->estaActiva()) {
            throw new DomainException('La zona indicada no existe o no está activa.');
        }

        if ($this->usuarios->buscarPorUsername($username) !== null) {
            throw new DomainException("Ya existe un usuario registrado con el username {$username}.");
        }

        $role = $this->roles->buscarPorNombre('ACOPIADOR');

        if ($role === null) {
            throw new DomainException('El rol ACOPIADOR no está configurado en el sistema.');
        }

        $roleId = $role->id() ?? throw new DomainException('El rol ACOPIADOR no está configurado en el sistema.');

        return $this->transacciones->run(function () use (
            $dni, $nombres, $apellidos, $telefono, $zonaId, $username, $password, $roleId, $fechaIngreso
        ): Acopiador {
            $usuario = $this->usuarios->guardar(new Usuario(
                id: null,
                roleId: $roleId,
                username: $username,
                password: $this->passwordHasher->hash($password),
                estado: EstadoUsuario::ACTIVO,
                debeCambiarPassword: true,
            ));

            $userId = $usuario->id() ?? throw new DomainException('No se pudo registrar el usuario del acopiador.');

            $fechaIngresoFinal = $fechaIngreso ?? new DateTimeImmutable;

            $acopiador = $this->acopiadores->guardar(new Acopiador(
                id: null,
                userId: $userId,
                zonaId: $zonaId,
                dni: $dni,
                nombres: $nombres,
                apellidos: $apellidos,
                telefono: $telefono,
                estado: EstadoUsuario::ACTIVO,
                fechaIngreso: $fechaIngresoFinal,
            ));

            $acopiadorId = $acopiador->id() ?? throw new DomainException('No se pudo registrar el acopiador.');

            $this->zonaHistorial->guardar(new AcopiadorZonaHistorial(
                id: null,
                acopiadorId: $acopiadorId,
                zonaId: $zonaId,
                fechaInicio: $fechaIngresoFinal,
                fechaFin: null,
                motivo: 'Registro inicial del acopiador.',
            ));

            return $acopiador;
        });
    }
}
