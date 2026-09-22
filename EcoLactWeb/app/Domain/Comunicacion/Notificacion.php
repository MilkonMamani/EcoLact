<?php

namespace App\Domain\Comunicacion;

use DateTimeImmutable;
use InvalidArgumentException;

final class Notificacion
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $userId,
        private readonly string $tipo,
        private readonly string $titulo,
        private readonly string $mensaje,
        private readonly ?string $referenciaTipo,
        private readonly ?int $referenciaId,
        private readonly bool $leido,
        private readonly ?DateTimeImmutable $fechaLectura = null,
    ) {
        if (trim($titulo) === '') {
            throw new InvalidArgumentException('El título de la notificación es obligatorio.');
        }

        if (trim($mensaje) === '') {
            throw new InvalidArgumentException('El mensaje de la notificación es obligatorio.');
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

    public function tipo(): string
    {
        return $this->tipo;
    }

    public function titulo(): string
    {
        return $this->titulo;
    }

    public function mensaje(): string
    {
        return $this->mensaje;
    }

    public function referenciaTipo(): ?string
    {
        return $this->referenciaTipo;
    }

    public function referenciaId(): ?int
    {
        return $this->referenciaId;
    }

    public function leido(): bool
    {
        return $this->leido;
    }

    public function fechaLectura(): ?DateTimeImmutable
    {
        return $this->fechaLectura;
    }
}
