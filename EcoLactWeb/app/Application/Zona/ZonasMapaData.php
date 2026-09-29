<?php

namespace App\Application\Zona;

use App\Domain\Zona\DelimitacionZona;

/**
 * Datos del mapa general de Zonas: cada zona con su color, delimitación
 * GeoJSON (null si todavía no se dibujó), punto de la etiqueta y métricas,
 * y los proveedores asignados que tienen coordenadas registradas.
 *
 * @phpstan-type ZonaMapa array{id: int, nombre: string, estado: string, color: string, geometry: ?array<string, mixed>, etiqueta: ?array{lat: float, lng: float}, proveedores: int, acopiadores: int, litrosSemana: float, ultimaEntrega: ?string, proveedoresConCoordenadas: int}
 * @phpstan-type ProveedorMapa array{id: int, nombre: string, dni: string, zonaId: int, zonaNombre: string, lat: float, lng: float, ultimaEntrega: ?string, litrosSemana: float, fueraDeDelimitacion: bool}
 */
final readonly class ZonasMapaData
{
    /**
     * @param  list<ZonaMapa>  $zonas
     * @param  list<ProveedorMapa>  $proveedores
     */
    public function __construct(
        public array $zonas,
        public array $proveedores,
    ) {}

    public function hayDelimitaciones(): bool
    {
        return array_filter($this->zonas, fn (array $zona): bool => $zona['geometry'] !== null) !== [];
    }

    /** @return array{tipoDelimitacion: string, zonas: list<ZonaMapa>, proveedores: list<ProveedorMapa>} */
    public function toArray(): array
    {
        return ['tipoDelimitacion' => DelimitacionZona::TIPO, 'zonas' => $this->zonas, 'proveedores' => $this->proveedores];
    }
}
