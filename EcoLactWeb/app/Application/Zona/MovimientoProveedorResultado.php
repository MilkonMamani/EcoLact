<?php

namespace App\Application\Zona;

/**
 * Resultado de MoverProveedorDeZona: dónde estaba el proveedor y dónde quedó
 * (zona, ruta y punto de recolección). cambioZona es false cuando la nueva
 * ubicación sigue dentro de la delimitación de su zona actual.
 */
final readonly class MovimientoProveedorResultado
{
    public function __construct(
        public int $proveedorId,
        public string $proveedorNombre,
        public int $zonaAnteriorId,
        public string $zonaAnteriorNombre,
        public int $zonaNuevaId,
        public string $zonaNuevaNombre,
        public int $rutaId,
        public ?float $latitudAnterior,
        public ?float $longitudAnterior,
        public float $latitud,
        public float $longitud,
    ) {}

    public function cambioZona(): bool
    {
        return $this->zonaAnteriorId !== $this->zonaNuevaId;
    }

    /**
     * @return array{proveedorId: int, cambioZona: bool, zonaAnterior: array{id: int, nombre: string}, zonaNueva: array{id: int, nombre: string}, rutaId: int, lat: float, lng: float}
     */
    public function toArray(): array
    {
        return [
            'proveedorId' => $this->proveedorId,
            'cambioZona' => $this->cambioZona(),
            'zonaAnterior' => ['id' => $this->zonaAnteriorId, 'nombre' => $this->zonaAnteriorNombre],
            'zonaNueva' => ['id' => $this->zonaNuevaId, 'nombre' => $this->zonaNuevaNombre],
            'rutaId' => $this->rutaId,
            'lat' => $this->latitud,
            'lng' => $this->longitud,
        ];
    }
}
