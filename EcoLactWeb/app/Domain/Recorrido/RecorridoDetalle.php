<?php

namespace App\Domain\Recorrido;

use App\Domain\Shared\Enums\EstadoParadaRecorrido;
use InvalidArgumentException;

/**
 * Una parada de un Recorrido: copia inmutable (tomada al crear el
 * recorrido) del proveedor, su orden de visita y su ubicación habitual de
 * entrega, más el estado de esa visita puntual. Esta copia es la que
 * permite que un recorrido histórico se siga viendo igual aunque después
 * cambien la ruta, la ubicación o la asignación real del proveedor.
 */
final class RecorridoDetalle
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $recorridoId,
        private readonly int $proveedorId,
        private readonly ?int $rutaDetalleId,
        private readonly int $ordenVisita,
        private readonly ?float $latitud,
        private readonly ?float $longitud,
        private readonly ?string $referenciaUbicacion,
        private readonly EstadoParadaRecorrido $estado,
        private readonly ?string $observacion = null,
    ) {
        if ($ordenVisita < 1) {
            throw new InvalidArgumentException('El orden de visita debe ser un entero positivo.');
        }

        if (($latitud === null) !== ($longitud === null)) {
            throw new InvalidArgumentException('La latitud y la longitud deben indicarse juntas o dejarse ambas vacías.');
        }

        if ($latitud !== null && ($latitud < -90 || $latitud > 90)) {
            throw new InvalidArgumentException('La latitud debe estar entre -90 y 90.');
        }

        if ($longitud !== null && ($longitud < -180 || $longitud > 180)) {
            throw new InvalidArgumentException('La longitud debe estar entre -180 y 180.');
        }
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function recorridoId(): int
    {
        return $this->recorridoId;
    }

    public function proveedorId(): int
    {
        return $this->proveedorId;
    }

    public function rutaDetalleId(): ?int
    {
        return $this->rutaDetalleId;
    }

    public function ordenVisita(): int
    {
        return $this->ordenVisita;
    }

    public function latitud(): ?float
    {
        return $this->latitud;
    }

    public function longitud(): ?float
    {
        return $this->longitud;
    }

    public function referenciaUbicacion(): ?string
    {
        return $this->referenciaUbicacion;
    }

    public function estado(): EstadoParadaRecorrido
    {
        return $this->estado;
    }

    public function observacion(): ?string
    {
        return $this->observacion;
    }

    public function tieneUbicacion(): bool
    {
        return $this->latitud !== null && $this->longitud !== null;
    }
}
