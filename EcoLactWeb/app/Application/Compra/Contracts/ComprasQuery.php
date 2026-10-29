<?php

namespace App\Application\Compra\Contracts;

use App\Application\Compra\CompraFila;
use App\Application\Compra\ComprasFiltro;
use App\Application\Compra\ComprasResumen;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ComprasQuery
{
    /** Indicadores con consultas agregadas (no se cargan las compras). */
    public function resumen(ComprasFiltro $filtro): ComprasResumen;

    /**
     * Historial, de la compra más reciente a la más antigua.
     *
     * @return LengthAwarePaginator<int, CompraFila>
     */
    public function listar(ComprasFiltro $filtro): LengthAwarePaginator;
}
