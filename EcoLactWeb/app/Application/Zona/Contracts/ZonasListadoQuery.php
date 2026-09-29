<?php

namespace App\Application\Zona\Contracts;

use App\Application\Zona\ZonasFiltro;
use App\Application\Zona\ZonasListadoData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ZonasListadoQuery
{
    public function obtener(ZonasFiltro $filtro): ZonasListadoData;

    /**
     * Pantalla de administración: una página de zonas con sus proveedores
     * asignados (por ruta), acopiadores, litros de la semana EcoLact en curso
     * (jueves a miércoles, sin entregas anuladas) y su última entrega.
     *
     * @return LengthAwarePaginator<int, array{id: int, nombre: string, descripcion: ?string, proveedores: int, acopiadores: int, litrosSemana: float, ultimaEntrega: ?string, estado: string}>
     */
    public function paginar(ZonasFiltro $filtro, int $porPagina, string $nombrePagina = 'pagina'): LengthAwarePaginator;
}
