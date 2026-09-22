<?php

namespace App\Domain\Recorrido;

use App\Domain\Shared\Enums\EstadoRecorrido;
use DateTimeImmutable;

/**
 * Ejecución concreta de una Ruta en una fecha operativa determinada,
 * asignada a un Acopiador. No debe confundirse con la Ruta (la
 * configuración de proveedores y su orden), que puede seguir cambiando sin
 * afectar Recorridos ya creados: RecorridoDetalle conserva su propia copia
 * de proveedor/orden/ubicación tomada en el momento de crear el recorrido.
 */
final class RecorridoAcopio
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $rutaId,
        private readonly int $acopiadorId,
        private readonly DateTimeImmutable $fechaOperativa,
        private readonly EstadoRecorrido $estado,
        private readonly ?DateTimeImmutable $iniciadoEn = null,
        private readonly ?DateTimeImmutable $finalizadoEn = null,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function rutaId(): int
    {
        return $this->rutaId;
    }

    public function acopiadorId(): int
    {
        return $this->acopiadorId;
    }

    public function fechaOperativa(): DateTimeImmutable
    {
        return $this->fechaOperativa;
    }

    public function estado(): EstadoRecorrido
    {
        return $this->estado;
    }

    public function iniciadoEn(): ?DateTimeImmutable
    {
        return $this->iniciadoEn;
    }

    public function finalizadoEn(): ?DateTimeImmutable
    {
        return $this->finalizadoEn;
    }

    public function estaAbierto(): bool
    {
        return $this->estado->estaAbierto();
    }
}
