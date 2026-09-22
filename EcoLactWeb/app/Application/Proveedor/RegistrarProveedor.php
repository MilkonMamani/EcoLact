<?php

namespace App\Application\Proveedor;

use App\Application\Shared\TransactionManager;
use App\Domain\Auth\PasswordHasher;
use App\Domain\Auth\Repositories\RoleRepository;
use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Auth\Usuario;
use App\Domain\Proveedor\Proveedor;
use App\Domain\Proveedor\ProveedorRepository;
use App\Domain\Proveedor\ProveedorZonaHistorial;
use App\Domain\Proveedor\ProveedorZonaHistorialRepository;
use App\Domain\Shared\Enums\EstadoUsuario;
use App\Domain\Zona\ZonaRepository;
use DateTimeImmutable;
use DomainException;

/**
 * Registra un nuevo proveedor coordinando la creación de su Usuario de
 * acceso y la validación de DNI, independientemente de su zona: la zona ya
 * no se captura en este formulario, se deriva de la asignación a una ruta
 * que se hace luego desde el módulo Zonas (ver
 * App\Application\Ruta\AsignarProveedorARuta). $zonaId se conserva como
 * parámetro opcional únicamente para flujos de migración/import que ya
 * conozcan la zona de antemano; el registro normal no lo envía.
 *
 * Nota de diseño: la creación del Usuario y el hash de la contraseña se
 * coordinan aquí porque un proveedor no existe sin sus credenciales de acceso
 * (relación 1 a 1 obligatoria por esquema: proveedores.user_id es único y no
 * nulo). Application no depende de Hash::/Auth:: de Laravel directamente: el
 * hashing se delega al contrato de Domain PasswordHasher, implementado en
 * Infrastructure. La contraseña en texto plano nunca se persiste.
 */
final class RegistrarProveedor
{
    public function __construct(
        private readonly ProveedorRepository $proveedores,
        private readonly UsuarioRepository $usuarios,
        private readonly ZonaRepository $zonas,
        private readonly ProveedorZonaHistorialRepository $zonaHistorial,
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
        ?int $zonaId = null,
        ?DateTimeImmutable $fechaRegistro = null,
    ): Proveedor {
        if ($this->proveedores->buscarPorDni($dni) !== null) {
            throw new DomainException("Ya existe un proveedor registrado con el DNI {$dni}.");
        }

        if ($zonaId !== null) {
            $zona = $this->zonas->buscarPorId($zonaId);

            if ($zona === null || ! $zona->estaActiva()) {
                throw new DomainException('La zona indicada no existe o no está activa.');
            }
        }

        if ($this->usuarios->buscarPorUsername($username) !== null) {
            throw new DomainException("Ya existe un usuario registrado con el username {$username}.");
        }

        $role = $this->roles->buscarPorNombre('PROVEEDOR');

        if ($role === null) {
            throw new DomainException('El rol PROVEEDOR no está configurado en el sistema.');
        }

        $roleId = $role->id() ?? throw new DomainException('El rol PROVEEDOR no está configurado en el sistema.');

        return $this->transacciones->run(function () use (
            $dni, $nombres, $apellidos, $telefono, $zonaId, $username, $password, $roleId, $fechaRegistro,
        ): Proveedor {
            $usuario = $this->usuarios->guardar(new Usuario(
                id: null,
                roleId: $roleId,
                username: $username,
                password: $this->passwordHasher->hash($password),
                estado: EstadoUsuario::ACTIVO,
                debeCambiarPassword: true,
            ));

            $userId = $usuario->id() ?? throw new DomainException('No se pudo registrar el usuario del proveedor.');

            $fechaRegistro ??= new DateTimeImmutable;

            $proveedor = $this->proveedores->guardar(new Proveedor(
                id: null,
                userId: $userId,
                zonaId: $zonaId,
                dni: $dni,
                nombres: $nombres,
                apellidos: $apellidos,
                telefono: $telefono,
                qrToken: $this->generarQrToken(),
                estado: EstadoUsuario::ACTIVO,
                fechaRegistro: $fechaRegistro,
            ));

            $proveedorId = $proveedor->id() ?? throw new DomainException('No se pudo registrar el proveedor.');

            if ($zonaId !== null) {
                $this->zonaHistorial->guardar(new ProveedorZonaHistorial(
                    id: null,
                    proveedorId: $proveedorId,
                    zonaId: $zonaId,
                    fechaInicio: $fechaRegistro,
                    fechaFin: null,
                    motivo: 'Registro inicial del proveedor.',
                ));
            }

            return $proveedor;
        });
    }

    /**
     * Genera un identificador único (UUID v4) que luego representará el
     * código QR estático del proveedor. La generación de la imagen QR en sí
     * queda fuera de esta etapa.
     */
    private function generarQrToken(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
