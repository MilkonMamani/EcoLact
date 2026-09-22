<?php

namespace App\Domain\Auth;

use App\Domain\Shared\Enums\EstadoUsuario;
use DateTimeImmutable;
use InvalidArgumentException;

final class Usuario
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $roleId,
        private readonly string $username,
        private readonly string $password,
        private readonly EstadoUsuario $estado,
        private readonly bool $debeCambiarPassword,
        private readonly ?DateTimeImmutable $ultimoAcceso = null,
    ) {
        if (trim($username) === '') {
            throw new InvalidArgumentException('El username del usuario es obligatorio.');
        }

        if (trim($password) === '') {
            throw new InvalidArgumentException('La contraseña del usuario es obligatoria.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function roleId(): int
    {
        return $this->roleId;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function password(): string
    {
        return $this->password;
    }

    public function estado(): EstadoUsuario
    {
        return $this->estado;
    }

    public function debeCambiarPassword(): bool
    {
        return $this->debeCambiarPassword;
    }

    public function ultimoAcceso(): ?DateTimeImmutable
    {
        return $this->ultimoAcceso;
    }

    public function estaActivo(): bool
    {
        return $this->estado === EstadoUsuario::ACTIVO;
    }
}
