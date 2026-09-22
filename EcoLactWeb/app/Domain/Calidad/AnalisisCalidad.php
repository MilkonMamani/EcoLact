<?php

namespace App\Domain\Calidad;

use App\Domain\Shared\Enums\EstadoAnalisis;
use App\Domain\Shared\Enums\ResultadoCalidad;
use DateTimeImmutable;
use DomainException;

final class AnalisisCalidad
{
    private EstadoAnalisis $estado;

    private ?ResultadoCalidad $resultado;

    private ?int $confirmadoPorUserId;

    public function __construct(
        private readonly ?int $id,
        private readonly int $entregaId,
        private readonly ?string $imagenPath,
        EstadoAnalisis $estado,
        ?ResultadoCalidad $resultado,
        private readonly DateTimeImmutable $fechaAnalisis,
        ?int $confirmadoPorUserId,
        private readonly ?string $observacion = null,
    ) {
        $this->estado = $estado;
        $this->resultado = $resultado;
        $this->confirmadoPorUserId = $confirmadoPorUserId;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function entregaId(): int
    {
        return $this->entregaId;
    }

    public function imagenPath(): ?string
    {
        return $this->imagenPath;
    }

    public function estado(): EstadoAnalisis
    {
        return $this->estado;
    }

    public function resultado(): ?ResultadoCalidad
    {
        return $this->resultado;
    }

    public function fechaAnalisis(): DateTimeImmutable
    {
        return $this->fechaAnalisis;
    }

    public function confirmadoPorUserId(): ?int
    {
        return $this->confirmadoPorUserId;
    }

    public function observacion(): ?string
    {
        return $this->observacion;
    }

    public function estaConfirmado(): bool
    {
        return $this->estado === EstadoAnalisis::CONFIRMADO;
    }

    public function confirmar(ResultadoCalidad $resultado, int $confirmadoPorUserId): void
    {
        if ($this->estaConfirmado()) {
            throw new DomainException('Un análisis de calidad confirmado es inmutable y no puede modificarse.');
        }

        $this->estado = EstadoAnalisis::CONFIRMADO;
        $this->resultado = $resultado;
        $this->confirmadoPorUserId = $confirmadoPorUserId;
    }
}
