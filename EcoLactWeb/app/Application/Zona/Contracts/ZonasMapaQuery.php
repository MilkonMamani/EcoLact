<?php

namespace App\Application\Zona\Contracts;

use App\Application\Zona\ZonasFiltro;

/**
 * Lectura en bruto (solo SQL) de lo que necesita el mapa general de Zonas. La interpretación
 * de la geometría, el color, la etiqueta y los avisos la hace ZonaMapService.
 */
interface ZonasMapaQuery
{
    /**
     * Zonas que pasan el filtro con su color y GeoJSON tal como están en BD y las mismas
     * métricas de la tabla. ultimaEntrega es la fecha-hora cruda ('Y-m-d H:i:s') o null.
     *
     * @return list<array{id: int, nombre: string, estado: string, colorMapa: ?string, poligonoGeoJson: ?string, proveedores: int, acopiadores: int, litrosSemana: float, ultimaEntrega: ?string}>
     */
    public function zonas(ZonasFiltro $filtro): array;

    /**
     * Proveedores asignados a una ruta de esas zonas que tienen latitud y longitud
     * registradas (los demás no se dibujan), con su última entrega cruda y los litros
     * de la semana EcoLact en curso.
     *
     * @param  list<int>  $zonaIds
     * @return list<array{id: int, nombre: string, dni: string, zonaId: int, lat: float, lng: float, ultimaEntrega: ?string, litrosSemana: float}>
     */
    public function proveedoresConCoordenadas(array $zonaIds): array;
}
