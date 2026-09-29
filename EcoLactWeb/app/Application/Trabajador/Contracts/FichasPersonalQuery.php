<?php

namespace App\Application\Trabajador\Contracts;

use App\Application\Trabajador\FichaPersonalPanelData;
use App\Application\Trabajador\FichasPersonalFiltro;
use App\Application\Trabajador\TipoFichaPersonal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface FichasPersonalQuery
{
    /**
     * Pantalla de administración: una página de fichas del tipo con el conteo de su actividad.
     *
     * @return LengthAwarePaginator<int, array{id: int, nombre: string, dni: string, telefono: ?string, actividad: int, estado: string}>
     */
    public function paginar(TipoFichaPersonal $tipo, FichasPersonalFiltro $filtro, int $porPagina, string $nombrePagina = 'pagina'): LengthAwarePaginator;

    /**
     * Todo lo que muestra el panel lateral; null si no existe. $tipoCliente
     * (PROVEEDOR, PUBLICO o MAYORISTA) solo filtra la actividad de PERSONAL_VENTAS.
     */
    public function panel(TipoFichaPersonal $tipo, int $fichaId, ?string $tipoCliente = null): ?FichaPersonalPanelData;
}
