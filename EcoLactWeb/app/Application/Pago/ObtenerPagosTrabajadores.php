<?php

namespace App\Application\Pago;

use App\Application\Pago\Contracts\TrabajadoresPagoListadoQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Datos de la pantalla "Pagar a trabajadores": sueldos del personal por periodo. */
final class ObtenerPagosTrabajadores
{
    public function __construct(private readonly TrabajadoresPagoListadoQuery $listado) {}

    /**
     * @return LengthAwarePaginator<int, TrabajadorPagoFila>
     */
    public function execute(TrabajadoresPagoFiltro $filtro): LengthAwarePaginator
    {
        return $this->listado->obtener($filtro);
    }
}
