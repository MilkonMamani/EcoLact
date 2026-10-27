<?php

namespace App\Domain\Calidad;

use App\Domain\Shared\Enums\EstadoMuestreo;
use App\Domain\Shared\Enums\MetodoSeleccionMuestreo;
use App\Domain\Shared\Enums\TipoControlMuestreo;
use DateTimeImmutable;
use DomainException;

/**
 * Una entrega elegida para control de calidad. Calidad trabaja por muestreo:
 * solo las entregas con muestreo pasan a "Muestras pendientes" y se analizan;
 * el resto nunca aparece como pendiente de análisis en el móvil.
 *
 * Fechas: fechaSeleccion y fechaCapturaLocal son la hora REAL del evento en
 * el teléfono (aunque se haya sincronizado más tarde); la hora de llegada al
 * servidor queda en created_at/updated_at.
 */
final class MuestreoCalidad
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $entregaId,
        private readonly int $seleccionadoPorUserId,
        private readonly MetodoSeleccionMuestreo $metodoSeleccion,
        private readonly ?TipoControlMuestreo $tipoControl,
        private readonly DateTimeImmutable $fechaSeleccion,
        private EstadoMuestreo $estado,
        private readonly ?string $observacion,
        private ?DateTimeImmutable $fechaCapturaLocal = null,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function entregaId(): int
    {
        return $this->entregaId;
    }

    public function seleccionadoPorUserId(): int
    {
        return $this->seleccionadoPorUserId;
    }

    public function metodoSeleccion(): MetodoSeleccionMuestreo
    {
        return $this->metodoSeleccion;
    }

    public function tipoControl(): ?TipoControlMuestreo
    {
        return $this->tipoControl;
    }

    public function fechaSeleccion(): DateTimeImmutable
    {
        return $this->fechaSeleccion;
    }

    public function estado(): EstadoMuestreo
    {
        return $this->estado;
    }

    public function observacion(): ?string
    {
        return $this->observacion;
    }

    public function fechaCapturaLocal(): ?DateTimeImmutable
    {
        return $this->fechaCapturaLocal;
    }

    public function estaAnalizada(): bool
    {
        return $this->estado === EstadoMuestreo::ANALIZADA;
    }

    public function estaPendiente(): bool
    {
        return $this->estado->estaPendiente();
    }

    /** El analista empezó a registrar valores (borrador del análisis). */
    public function iniciarAnalisis(?DateTimeImmutable $fechaCapturaLocal = null): void
    {
        if ($this->estaAnalizada()) {
            throw new DomainException('Esta muestra ya fue analizada.');
        }

        if ($this->estado === EstadoMuestreo::CANCELADA) {
            throw new DomainException('Esta muestra fue cancelada porque su entrega se anuló.');
        }

        $this->estado = EstadoMuestreo::EN_ANALISIS;
        $this->fechaCapturaLocal ??= $fechaCapturaLocal;
    }

    public function marcarAnalizada(): void
    {
        $this->estado = EstadoMuestreo::ANALIZADA;
    }

    /** Solo una muestra todavía pendiente se cancela; una ya analizada conserva su historial. */
    public function cancelar(): void
    {
        if ($this->estaPendiente()) {
            $this->estado = EstadoMuestreo::CANCELADA;
        }
    }
}
