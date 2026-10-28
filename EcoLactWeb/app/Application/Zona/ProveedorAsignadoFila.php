<?php

namespace App\Application\Zona;

/**
 * Fila de la tabla "Proveedores de la zona": un proveedor con su asignación
 * vigente (zona, ruta, orden y punto de recolección). Los campos de
 * asignación son null cuando el proveedor todavía no está en ninguna ruta.
 *
 * zonaRegistrada* es la caché proveedores.zona_id: un proveedor cargado con su
 * zona pero aún sin ruta la trae, y el modal de asignación la propone como
 * punto de partida. No es la asignación: esa la da la ruta.
 */
final readonly class ProveedorAsignadoFila
{
    public function __construct(
        public int $proveedorId,
        public string $nombre,
        public string $dni,
        public ?int $detalleId,
        public ?int $zonaId,
        public ?string $zonaNombre,
        public ?int $rutaId,
        public ?string $rutaNombre,
        public ?int $ordenVisita,
        public ?float $latitud,
        public ?float $longitud,
        public ?string $referenciaUbicacion,
        public ?int $zonaRegistradaId = null,
        public ?string $zonaRegistradaNombre = null,
    ) {}

    public function estaAsignado(): bool
    {
        return $this->detalleId !== null;
    }

    public function tieneUbicacion(): bool
    {
        return $this->latitud !== null && $this->longitud !== null;
    }

    /**
     * Datos mínimos que el modal de asignación necesita para precargarse.
     *
     * @return array<string, mixed>
     */
    public function paraModal(): array
    {
        return [
            'proveedorId' => $this->proveedorId,
            'nombre' => $this->nombre,
            'dni' => $this->dni,
            'zonaId' => $this->zonaId,
            'rutaId' => $this->rutaId,
            'zonaNombre' => $this->zonaNombre,
            'rutaNombre' => $this->rutaNombre,
            'latitud' => $this->latitud,
            'longitud' => $this->longitud,
            'referencia' => $this->referenciaUbicacion,
            'zonaRegistradaId' => $this->zonaRegistradaId,
            'zonaRegistradaNombre' => $this->zonaRegistradaNombre,
        ];
    }
}
