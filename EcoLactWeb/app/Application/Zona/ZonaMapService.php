<?php

namespace App\Application\Zona;

use App\Application\Shared\Reloj;
use App\Application\Zona\Contracts\ZonasMapaQuery;
use App\Domain\Zona\DelimitacionZona;

/**
 * Arma el mapa general de Zonas:
 *
 *   MySQL zonas.poligono_geojson ─▶ ZonasMapaQuery (SQL) ─▶ ZonaMapService ─▶ ZonasMapaData
 *   (JSON en la vista) ─▶ Google Maps: Polygon + etiqueta + pin por proveedor.
 *
 * Aquí se interpreta la geometría guardada (DelimitacionZona), se resuelve el color, se
 * calcula el punto de la etiqueta dentro del polígono y se marca a los proveedores cuya
 * ubicación cae fuera de la delimitación de su zona. El contador de la etiqueta es
 * proveedores_count (todos los asignados), nunca la cantidad de pines.
 */
final class ZonaMapService
{
    public function __construct(
        private readonly ZonasMapaQuery $consulta,
        private readonly Reloj $reloj,
    ) {}

    public function obtener(ZonasFiltro $filtro): ZonasMapaData
    {
        $hoy = $this->reloj->hoy();
        $zonas = [];
        $delimitaciones = [];

        foreach ($this->consulta->zonas($filtro) as $zona) {
            $delimitacion = DelimitacionZona::desdeGeoJsonONull($zona['poligonoGeoJson']);
            $delimitaciones[$zona['id']] = $delimitacion;
            $zonas[$zona['id']] = [
                'id' => $zona['id'],
                'nombre' => $zona['nombre'],
                'estado' => $zona['estado'],
                'color' => ColorZona::resolver($zona['colorMapa'], $zona['id']),
                'geometry' => $delimitacion?->aGeoJson(),
                'etiqueta' => $delimitacion?->puntoEtiqueta(),
                'proveedores' => $zona['proveedores'],
                'acopiadores' => $zona['acopiadores'],
                'litrosSemana' => $zona['litrosSemana'],
                'ultimaEntrega' => $zona['ultimaEntrega'] !== null ? MomentoEntrega::etiqueta($zona['ultimaEntrega'], $hoy) : null,
                'proveedoresConCoordenadas' => 0,
            ];
        }

        $proveedores = [];

        foreach ($this->consulta->proveedoresConCoordenadas(array_keys($zonas)) as $proveedor) {
            $delimitacion = $delimitaciones[$proveedor['zonaId']];
            $zonas[$proveedor['zonaId']]['proveedoresConCoordenadas']++;
            $proveedores[] = [
                ...$proveedor,
                'zonaNombre' => $zonas[$proveedor['zonaId']]['nombre'],
                'ultimaEntrega' => $proveedor['ultimaEntrega'] !== null ? MomentoEntrega::etiqueta($proveedor['ultimaEntrega'], $hoy) : null,
                'fueraDeDelimitacion' => $delimitacion !== null && ! $delimitacion->contiene($proveedor['lat'], $proveedor['lng']),
            ];
        }

        return new ZonasMapaData(zonas: array_values($zonas), proveedores: $proveedores);
    }
}
