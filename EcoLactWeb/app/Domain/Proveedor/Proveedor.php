<?php

namespace App\Domain\Proveedor;

use App\Domain\Shared\Enums\EstadoUsuario;
use DateTimeImmutable;
use InvalidArgumentException;

final class Proveedor
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $userId,
        private readonly ?int $zonaId,
        private readonly string $dni,
        private readonly string $nombres,
        private readonly string $apellidos,
        private readonly ?string $telefono,
        private readonly string $qrToken,
        private readonly EstadoUsuario $estado,
        private readonly DateTimeImmutable $fechaRegistro,
        private readonly int $advertenciasCount = 0,
    ) {
        if (trim($dni) === '') {
            throw new InvalidArgumentException('El DNI del proveedor es obligatorio.');
        }

        if (trim($nombres) === '' || trim($apellidos) === '') {
            throw new InvalidArgumentException('El nombre y apellido del proveedor son obligatorios.');
        }

        if (trim($qrToken) === '') {
            throw new InvalidArgumentException('El token QR del proveedor es obligatorio.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    /**
     * Zona vigente del proveedor. Puede ser null: un proveedor recién
     * registrado todavía no tiene una ruta asignada. Este valor es una
     * caché sincronizada por el módulo Zonas/Rutas (ver
     * App\Application\Ruta\AsignarProveedorARuta); la fuente de verdad real
     * de la asignación es ruta_detalles/rutas.
     */
    public function zonaId(): ?int
    {
        return $this->zonaId;
    }

    public function dni(): string
    {
        return $this->dni;
    }

    public function nombres(): string
    {
        return $this->nombres;
    }

    public function apellidos(): string
    {
        return $this->apellidos;
    }

    public function telefono(): ?string
    {
        return $this->telefono;
    }

    public function qrToken(): string
    {
        return $this->qrToken;
    }

    public function estado(): EstadoUsuario
    {
        return $this->estado;
    }

    public function fechaRegistro(): DateTimeImmutable
    {
        return $this->fechaRegistro;
    }

    public function nombreCompleto(): string
    {
        return trim("{$this->nombres} {$this->apellidos}");
    }

    public function advertenciasCount(): int
    {
        return $this->advertenciasCount;
    }
}
