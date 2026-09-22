<?php

namespace App\Domain\Comunicacion;

use DateTimeImmutable;
use InvalidArgumentException;

final class Comunicado
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $titulo,
        private readonly string $contenido,
        private readonly string $tipo,
        private readonly string $alcance,
        private readonly ?int $zonaId,
        private readonly DateTimeImmutable $fechaPublicacion,
        private readonly ?DateTimeImmutable $fechaEvento,
        private readonly int $creadoPorUserId,
        private readonly string $estado,
    ) {
        if (trim($titulo) === '') {
            throw new InvalidArgumentException('El título del comunicado es obligatorio.');
        }

        if (trim($contenido) === '') {
            throw new InvalidArgumentException('El contenido del comunicado es obligatorio.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function titulo(): string
    {
        return $this->titulo;
    }

    public function contenido(): string
    {
        return $this->contenido;
    }

    public function tipo(): string
    {
        return $this->tipo;
    }

    public function alcance(): string
    {
        return $this->alcance;
    }

    public function zonaId(): ?int
    {
        return $this->zonaId;
    }

    public function fechaPublicacion(): DateTimeImmutable
    {
        return $this->fechaPublicacion;
    }

    public function fechaEvento(): ?DateTimeImmutable
    {
        return $this->fechaEvento;
    }

    public function creadoPorUserId(): int
    {
        return $this->creadoPorUserId;
    }

    public function estado(): string
    {
        return $this->estado;
    }
}
