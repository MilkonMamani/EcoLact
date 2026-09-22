<?php

namespace App\Domain\Ruta;

use InvalidArgumentException;

/**
 * Representa la asignación VIGENTE de un proveedor a una ruta (fila de la
 * lista "carrito" de la ruta), con su orden de visita y el punto de
 * recolección (latitud/longitud/referencia) donde se le recoge la leche
 * dentro de esa ruta.
 *
 * Esta tabla solo contiene la configuración vigente: cuando un proveedor se
 * traslada o se retira de una ruta, su fila se elimina de aquí y el
 * movimiento queda registrado en ProveedorRutaHistorial (igual patrón que
 * proveedores.zona_id / proveedor_zona_historial ya usan en este proyecto).
 * Por eso las columnas únicas de base de datos (un proveedor con una sola
 * asignación vigente entre todas las rutas; un orden sin repetir dentro de
 * una misma ruta) pueden ser restricciones simples: nunca conviven filas
 * vigentes e históricas en la misma tabla.
 *
 * La ubicación es opcional (una asignación puede crearse sin ella), pero
 * latitud y longitud siempre se indican juntas.
 */
final class RutaDetalle
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $rutaId,
        private readonly int $proveedorId,
        private readonly int $ordenVisita,
        private readonly ?float $latitud = null,
        private readonly ?float $longitud = null,
        private readonly ?string $referenciaUbicacion = null,
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

    public function rutaId(): int
    {
        return $this->rutaId;
    }

    public function proveedorId(): int
    {
        return $this->proveedorId;
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

    public function tieneUbicacion(): bool
    {
        return $this->latitud !== null && $this->longitud !== null;
    }

    public function conUbicacion(?float $latitud, ?float $longitud, ?string $referenciaUbicacion): self
    {
        return new self(
            id: $this->id,
            rutaId: $this->rutaId,
            proveedorId: $this->proveedorId,
            ordenVisita: $this->ordenVisita,
            latitud: $latitud,
            longitud: $longitud,
            referenciaUbicacion: $referenciaUbicacion,
        );
    }
}
